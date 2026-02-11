<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

class TokenUsage
{
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly ?int $reasoningTokens = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? $data['promptTokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? $data['completionTokens'] ?? 0,
            reasoningTokens: $data['reasoning_tokens'] ?? $data['reasoningTokens'] ?? null,
        );
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens + ($this->reasoningTokens ?? 0);
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'total_tokens' => $this->totalTokens(),
        ];
    }
}
