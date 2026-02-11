<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

use Frolax\LlmTokenKit\Enums\ContextStrategy;
use Frolax\LlmTokenKit\Enums\ToolMessagePolicy;

class ContextBuildRequest
{
    public function __construct(
        public readonly ?string $system = null,
        public readonly ?string $memorySummary = null,
        public readonly array $historyMessages = [],
        public readonly string $newUserMessage = '',
        public readonly ModelRef $modelRef = new ModelRef(provider: 'openai', model: 'gpt-4'),
        public readonly ?int $tokenBudget = null,
        public readonly int $reservedOutputTokens = 4096,
        public readonly int $windowSize = 50,
        public readonly ContextStrategy $strategy = ContextStrategy::RollingWindow,
        public readonly bool $includeToolMessages = false,
        public readonly ToolMessagePolicy $toolMessagePolicy = ToolMessagePolicy::Exclude,
        public readonly ?string $languageHint = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $rawModelRef = $data['modelRef'] ?? $data['model_ref'] ?? null;
        $modelRef = $rawModelRef instanceof ModelRef
            ? $rawModelRef
            : ($rawModelRef !== null
                ? ModelRef::fromArray($rawModelRef)
                : new ModelRef(provider: 'openai', model: 'gpt-4'));

        $strategy = $data['strategy'] ?? ContextStrategy::RollingWindow;
        if (is_string($strategy)) {
            $strategy = ContextStrategy::from($strategy);
        }

        $toolMessagePolicy = $data['tool_message_policy'] ?? $data['toolMessagePolicy'] ?? ToolMessagePolicy::Exclude;
        if (is_string($toolMessagePolicy)) {
            $toolMessagePolicy = ToolMessagePolicy::from($toolMessagePolicy);
        }

        return new self(
            system: $data['system'] ?? null,
            memorySummary: $data['memory_summary'] ?? $data['memorySummary'] ?? null,
            historyMessages: $data['history_messages'] ?? $data['historyMessages'] ?? [],
            newUserMessage: $data['new_user_message'] ?? $data['newUserMessage'] ?? '',
            modelRef: $modelRef,
            tokenBudget: $data['token_budget'] ?? $data['tokenBudget'] ?? null,
            reservedOutputTokens: $data['reserved_output_tokens'] ?? $data['reservedOutputTokens'] ?? 4096,
            windowSize: $data['window_size'] ?? $data['windowSize'] ?? 50,
            strategy: $strategy,
            includeToolMessages: $data['include_tool_messages'] ?? $data['includeToolMessages'] ?? false,
            toolMessagePolicy: $toolMessagePolicy,
            languageHint: $data['language_hint'] ?? $data['languageHint'] ?? null,
        );
    }

    /**
     * Get the effective token budget (explicit or derived from model context limit).
     */
    public function effectiveTokenBudget(): int
    {
        return $this->tokenBudget ?? ($this->modelRef->contextLimit - $this->reservedOutputTokens);
    }

    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'memory_summary' => $this->memorySummary,
            'history_messages' => $this->historyMessages,
            'new_user_message' => $this->newUserMessage,
            'model_ref' => $this->modelRef->toArray(),
            'token_budget' => $this->tokenBudget,
            'reserved_output_tokens' => $this->reservedOutputTokens,
            'window_size' => $this->windowSize,
            'strategy' => $this->strategy->value,
            'include_tool_messages' => $this->includeToolMessages,
            'tool_message_policy' => $this->toolMessagePolicy->value,
            'language_hint' => $this->languageHint,
        ];
    }
}
