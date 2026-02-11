<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Estimators;

use Frolax\LlmTokenKit\Contracts\TokenEstimatorInterface;
use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\TokenEstimate;
use Frolax\LlmTokenKit\Enums\Confidence;
use Frolax\LlmTokenKit\Enums\EstimationMethod;

class HeuristicEstimator implements TokenEstimatorInterface
{
    /**
     * Per-message overhead tokens (role tag, delimiters, etc.)
     */
    private const MESSAGE_OVERHEAD = 4;

    /**
     * Final reply priming tokens.
     */
    private const REPLY_PRIMING = 3;

    public function __construct(
        private readonly array $config = [],
    ) {}

    public function supports(ModelRef $model): bool
    {
        // Heuristic works for all models
        return true;
    }

    public function name(): string
    {
        return 'heuristic';
    }

    public function estimateText(string $text, ModelRef $model, array $opts = []): TokenEstimate
    {
        if ($text === '') {
            return new TokenEstimate(
                inputTokensEstimated: 0,
                notes: ['Empty input text'],
                confidence: Confidence::High,
                method: EstimationMethod::Heuristic,
            );
        }

        $notes = [];
        $languageHint = $opts['language_hint'] ?? null;

        $baseTokens = $this->estimateTokensForString($text, $languageHint, $notes);

        $confidence = $this->determineConfidence($text, $languageHint);

        return new TokenEstimate(
            inputTokensEstimated: max(1, (int) round($baseTokens)),
            notes: $notes,
            confidence: $confidence,
            method: EstimationMethod::Heuristic,
        );
    }

    public function estimateMessages(array $messages, ModelRef $model, array $opts = []): TokenEstimate
    {
        if (empty($messages)) {
            return new TokenEstimate(
                inputTokensEstimated: 0,
                notes: ['No messages provided'],
                confidence: Confidence::High,
                method: EstimationMethod::Heuristic,
            );
        }

        $notes = [];
        $languageHint = $opts['language_hint'] ?? null;
        $totalTokens = 0;

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $role = $message['role'] ?? 'user';

            // Per-message overhead (role, delimiters)
            $totalTokens += self::MESSAGE_OVERHEAD;

            // Role name token
            $totalTokens += 1;

            // Content tokens
            if (is_string($content) && $content !== '') {
                $msgNotes = [];
                $totalTokens += $this->estimateTokensForString($content, $languageHint, $msgNotes);
                foreach ($msgNotes as $note) {
                    if (! in_array($note, $notes, true)) {
                        $notes[] = $note;
                    }
                }
            }

            // Name field overhead if present
            if (isset($message['name'])) {
                $totalTokens += mb_strlen($message['name']) / 4 + 1;
            }
        }

        // Reply priming
        $totalTokens += self::REPLY_PRIMING;

        $notes[] = sprintf('Estimated %d messages with per-message overhead of %d tokens', count($messages), self::MESSAGE_OVERHEAD);

        $confidence = $this->determineConfidence(
            implode(' ', array_column($messages, 'content')),
            $languageHint,
        );

        return new TokenEstimate(
            inputTokensEstimated: max(1, (int) round($totalTokens)),
            notes: $notes,
            confidence: $confidence,
            method: EstimationMethod::Heuristic,
        );
    }

    /**
     * Core heuristic: estimate tokens for a string.
     */
    private function estimateTokensForString(string $text, ?string $languageHint, array &$notes): float
    {
        $charCount = mb_strlen($text);

        // Base: ~4 chars per token for English
        $tokens = $charCount / 4.0;

        // Detect and apply code/JSON penalty
        if ($this->isCodeOrJson($text)) {
            $penalty = $this->config('code_json_penalty', 1.3);
            $tokens *= $penalty;
            $notes[] = sprintf('Code/JSON detected: applied %.1fx penalty', $penalty);
        }

        // Non-Latin script detection + multiplier
        $scriptMultiplier = $this->detectScriptMultiplier($text, $languageHint, $notes);
        if ($scriptMultiplier > 1.0) {
            $tokens *= $scriptMultiplier;
        }

        return $tokens;
    }

    /**
     * Detect if text looks like code or JSON.
     */
    private function isCodeOrJson(string $text): bool
    {
        if (! $this->config('enable_code_json_penalty', true)) {
            return false;
        }

        // Check for JSON
        $trimmed = trim($text);
        if (
            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
        ) {
            return true;
        }

        // Count typical code symbols
        $symbolCount = preg_match_all('/[{}()\[\];=<>\/\\\\|&^~`]/', $text);
        $lineCount = max(1, substr_count($text, "\n") + 1);
        $symbolDensity = $symbolCount / max(1, mb_strlen($text));

        // If symbol density is high, treat as code
        return $symbolDensity > 0.05;
    }

    /**
     * Detect non-Latin scripts and return appropriate multiplier.
     */
    private function detectScriptMultiplier(string $text, ?string $languageHint, array &$notes): float
    {
        $multipliers = $this->config('language_multipliers', [
            'bn' => 2.5,  // Bangla
            'hi' => 2.0,  // Hindi
            'ar' => 2.0,  // Arabic
            'zh' => 1.5,  // Chinese
            'ja' => 1.5,  // Japanese
            'ko' => 1.5,  // Korean
            'th' => 2.0,  // Thai
        ]);

        // If language hint is provided, use it directly
        if ($languageHint && isset($multipliers[$languageHint])) {
            $multiplier = $multipliers[$languageHint];
            $notes[] = sprintf('Language hint "%s": applied %.1fx multiplier', $languageHint, $multiplier);

            return $multiplier;
        }

        // Auto-detect non-Latin scripts
        $nonLatinCount = 0;
        $totalChars = mb_strlen($text);

        if ($totalChars === 0) {
            return 1.0;
        }

        // Check for Bangla/Bengali script
        if (preg_match_all('/[\x{0980}-\x{09FF}]/u', $text, $matches)) {
            $banglaCount = count($matches[0]);
            if ($banglaCount / $totalChars > 0.3) {
                $multiplier = $multipliers['bn'] ?? 2.5;
                $notes[] = sprintf('Bengali script detected: applied %.1fx multiplier', $multiplier);

                return $multiplier;
            }
            $nonLatinCount += $banglaCount;
        }

        // Check for Devanagari (Hindi)
        if (preg_match_all('/[\x{0900}-\x{097F}]/u', $text, $matches)) {
            $hindiCount = count($matches[0]);
            if ($hindiCount / $totalChars > 0.3) {
                $multiplier = $multipliers['hi'] ?? 2.0;
                $notes[] = sprintf('Devanagari script detected: applied %.1fx multiplier', $multiplier);

                return $multiplier;
            }
            $nonLatinCount += $hindiCount;
        }

        // Check for Arabic script
        if (preg_match_all('/[\x{0600}-\x{06FF}]/u', $text, $matches)) {
            $arabicCount = count($matches[0]);
            if ($arabicCount / $totalChars > 0.3) {
                $multiplier = $multipliers['ar'] ?? 2.0;
                $notes[] = sprintf('Arabic script detected: applied %.1fx multiplier', $multiplier);

                return $multiplier;
            }
            $nonLatinCount += $arabicCount;
        }

        // Check for CJK
        if (preg_match_all('/[\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $text, $matches)) {
            $cjkCount = count($matches[0]);
            if ($cjkCount / $totalChars > 0.3) {
                $multiplier = $multipliers['zh'] ?? 1.5;
                $notes[] = sprintf('CJK script detected: applied %.1fx multiplier', $multiplier);

                return $multiplier;
            }
            $nonLatinCount += $cjkCount;
        }

        // Generic non-Latin fallback
        if ($nonLatinCount > 0 && ($nonLatinCount / $totalChars > 0.2)) {
            $notes[] = 'Mixed non-Latin script detected: applied 1.5x multiplier';

            return 1.5;
        }

        return 1.0;
    }

    /**
     * Determine confidence level based on text characteristics.
     */
    private function determineConfidence(string $text, ?string $languageHint): Confidence
    {
        $totalChars = mb_strlen($text);

        if ($totalChars === 0) {
            return Confidence::High;
        }

        // Non-Latin → low confidence
        if ($languageHint && in_array($languageHint, ['bn', 'hi', 'ar', 'zh', 'ja', 'ko', 'th'])) {
            return Confidence::Low;
        }

        // Auto-detect non-Latin
        $nonLatinCount = preg_match_all('/[^\x00-\x7F]/u', $text);
        if ($nonLatinCount / max(1, $totalChars) > 0.3) {
            return Confidence::Low;
        }

        // Code/JSON → medium
        if ($this->isCodeOrJson($text)) {
            return Confidence::Medium;
        }

        return Confidence::Medium;
    }

    /**
     * Get a config value with a default.
     */
    private function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? config("llm-tokenkit.estimation.{$key}", $default);
    }
}
