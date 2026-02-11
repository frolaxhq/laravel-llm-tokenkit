<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Context;

use Frolax\LlmTokenKit\Data\ContextBuildRequest;
use Frolax\LlmTokenKit\Data\ContextBuildResult;
use Frolax\LlmTokenKit\Data\TokenEstimate;
use Frolax\LlmTokenKit\Enums\Confidence;
use Frolax\LlmTokenKit\Enums\ContextStrategy;
use Frolax\LlmTokenKit\Enums\EstimationMethod;
use Frolax\LlmTokenKit\Enums\ToolMessagePolicy;
use Frolax\LlmTokenKit\Estimators\EstimatorChain;

class ContextBuilder
{
    public function __construct(
        private readonly EstimatorChain $estimatorChain,
    ) {}

    /**
     * Build context messages from a ContextBuildRequest.
     */
    public function build(ContextBuildRequest $request): ContextBuildResult
    {
        $warnings = [];
        $droppedCount = 0;

        // 1. Start with pinned messages (system + memory)
        $pinnedMessages = $this->buildPinnedMessages($request);

        // 2. Process history messages (apply tool message policy)
        $historyMessages = $this->processHistoryMessages($request, $warnings, $droppedCount);

        // 3. Apply strategy (rolling window or truncate by tokens)
        $selectedHistory = match ($request->strategy) {
            ContextStrategy::RollingWindow => $this->applyRollingWindow(
                $historyMessages,
                $request,
                $pinnedMessages,
                $warnings,
                $droppedCount,
            ),
            ContextStrategy::TruncateByTokens => $this->applyTruncateByTokens(
                $historyMessages,
                $request,
                $pinnedMessages,
                $warnings,
                $droppedCount,
            ),
        };

        // 4. Build final message array: pinned + selected history + new user message
        $finalMessages = $pinnedMessages;
        foreach ($selectedHistory as $msg) {
            $finalMessages[] = $msg;
        }

        // Always append new user message at the end (never duplicate)
        if ($request->newUserMessage !== '') {
            $finalMessages[] = [
                'role' => 'user',
                'content' => $request->newUserMessage,
            ];
        }

        // 5. Estimate tokens for final messages
        $tokenEstimate = $this->estimateMessages($finalMessages, $request);
        $wasTruncated = $droppedCount > 0;

        return new ContextBuildResult(
            messages: $finalMessages,
            tokenEstimate: $tokenEstimate,
            wasTruncated: $wasTruncated,
            droppedMessageCount: $droppedCount,
            warnings: $warnings,
        );
    }

    /**
     * Build pinned messages (system prompt + memory summary).
     */
    private function buildPinnedMessages(ContextBuildRequest $request): array
    {
        $pinned = [];

        if ($request->system !== null && $request->system !== '') {
            $pinned[] = [
                'role' => 'system',
                'content' => $request->system,
            ];
        }

        if ($request->memorySummary !== null && $request->memorySummary !== '') {
            $pinned[] = [
                'role' => 'system',
                'content' => "[Memory Summary]\n" . $request->memorySummary,
            ];
        }

        return $pinned;
    }

    /**
     * Process history messages applying tool message policy.
     * Does NOT include the new user message here.
     */
    private function processHistoryMessages(
        ContextBuildRequest $request,
        array &$warnings,
        int &$droppedCount,
    ): array {
        $processed = [];
        $toolTokenThreshold = (int) config('llm-tokenkit.context.tool_token_threshold', 200);

        foreach ($request->historyMessages as $message) {
            $role = $message['role'] ?? 'user';

            // Handle tool messages
            if ($role === 'tool' || $role === 'function') {
                if (! $request->includeToolMessages) {
                    $droppedCount++;

                    continue;
                }

                $processed[] = match ($request->toolMessagePolicy) {
                    ToolMessagePolicy::Exclude => (function () use (&$droppedCount) {
                        $droppedCount++;

                        return null;
                    })(),
                    ToolMessagePolicy::SummarizeOnly => [
                        'role' => $role,
                        'content' => 'Tool result omitted; available in app.',
                    ],
                    ToolMessagePolicy::IncludeSmall => (function () use ($message, $role, $toolTokenThreshold, &$droppedCount) {
                        $contentLength = mb_strlen($message['content'] ?? '');
                        $estimatedTokens = (int) ceil($contentLength / 4);

                        if ($estimatedTokens <= $toolTokenThreshold) {
                            return $message;
                        }

                        $droppedCount++;

                        return [
                            'role' => $role,
                            'content' => 'Tool result omitted (too large); available in app.',
                        ];
                    })(),
                };

                // Filter nulls (from the Exclude case)
                $processed = array_values(array_filter($processed, fn ($v) => $v !== null));

                continue;
            }

            $processed[] = $message;
        }

        return $processed;
    }

    /**
     * Rolling window: keep last N messages, then ensure token budget.
     */
    private function applyRollingWindow(
        array $historyMessages,
        ContextBuildRequest $request,
        array $pinnedMessages,
        array &$warnings,
        int &$droppedCount,
    ): array {
        $windowSize = $request->windowSize;
        $totalHistory = count($historyMessages);

        // Take last N messages
        if ($totalHistory > $windowSize) {
            $dropped = $totalHistory - $windowSize;
            $droppedCount += $dropped;
            $historyMessages = array_slice($historyMessages, -$windowSize);
            $warnings[] = sprintf('Rolling window: dropped %d oldest messages (window size %d)', $dropped, $windowSize);
        }

        // Now ensure token budget
        $budget = $request->effectiveTokenBudget();

        return $this->trimToTokenBudget($historyMessages, $request, $pinnedMessages, $budget, $warnings, $droppedCount);
    }

    /**
     * Truncate by tokens: progressively drop oldest messages until under budget.
     */
    private function applyTruncateByTokens(
        array $historyMessages,
        ContextBuildRequest $request,
        array $pinnedMessages,
        array &$warnings,
        int &$droppedCount,
    ): array {
        $budget = $request->effectiveTokenBudget();

        return $this->trimToTokenBudget($historyMessages, $request, $pinnedMessages, $budget, $warnings, $droppedCount);
    }

    /**
     * Trim history messages to fit within the token budget.
     * Drops oldest messages first until the total estimate is under budget.
     */
    private function trimToTokenBudget(
        array $historyMessages,
        ContextBuildRequest $request,
        array $pinnedMessages,
        int $budget,
        array &$warnings,
        int &$droppedCount,
    ): array {
        if (empty($historyMessages)) {
            return [];
        }

        // Build candidate: pinned + history + new user message
        $newUserMsg = $request->newUserMessage !== ''
            ? [['role' => 'user', 'content' => $request->newUserMessage]]
            : [];

        $allMessages = array_merge($pinnedMessages, $historyMessages, $newUserMsg);
        $estimate = $this->estimateMessages($allMessages, $request);

        // Remove oldest history messages until under budget
        while ($estimate->inputTokensEstimated > $budget && ! empty($historyMessages)) {
            array_shift($historyMessages);
            $droppedCount++;

            $allMessages = array_merge($pinnedMessages, $historyMessages, $newUserMsg);
            $estimate = $this->estimateMessages($allMessages, $request);
        }

        if ($estimate->inputTokensEstimated > $budget) {
            $warnings[] = sprintf(
                'Token budget (%d) still exceeded (%d tokens) after dropping all history messages',
                $budget,
                $estimate->inputTokensEstimated,
            );
        }

        return $historyMessages;
    }

    /**
     * Estimate token count for a set of messages.
     */
    private function estimateMessages(array $messages, ContextBuildRequest $request): TokenEstimate
    {
        $opts = [];
        if ($request->languageHint !== null) {
            $opts['language_hint'] = $request->languageHint;
        }

        try {
            return $this->estimatorChain->estimateMessages($messages, $request->modelRef, $opts);
        } catch (\Throwable) {
            // Fallback: rough estimate
            $totalContent = implode(' ', array_map(fn ($m) => $m['content'] ?? '', $messages));
            $estimated = (int) ceil(mb_strlen($totalContent) / 4);

            return new TokenEstimate(
                inputTokensEstimated: $estimated,
                notes: ['Fallback estimation used due to estimator error'],
                confidence: Confidence::Low,
                method: EstimationMethod::Heuristic,
            );
        }
    }
}
