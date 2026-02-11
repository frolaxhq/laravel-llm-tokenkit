<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

class ModelRef
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly int $contextLimit = 128_000,
        public readonly ?string $tokenizer = null,
        public readonly array $meta = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'] ?? 'unknown',
            model: $data['model'] ?? 'unknown',
            contextLimit: $data['context_limit'] ?? $data['contextLimit'] ?? 128_000,
            tokenizer: $data['tokenizer'] ?? null,
            meta: $data['meta'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'context_limit' => $this->contextLimit,
            'tokenizer' => $this->tokenizer,
            'meta' => $this->meta,
        ];
    }
}
