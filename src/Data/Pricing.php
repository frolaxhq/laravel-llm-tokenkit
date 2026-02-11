<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

class Pricing
{
    public function __construct(
        public readonly float $inputPer1m,
        public readonly float $outputPer1m,
        public readonly ?float $reasoningPer1m = null,
        public readonly string $currency = 'USD',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            inputPer1m: (float) ($data['input_per_1m'] ?? $data['inputPer1m'] ?? 0.0),
            outputPer1m: (float) ($data['output_per_1m'] ?? $data['outputPer1m'] ?? 0.0),
            reasoningPer1m: isset($data['reasoning_per_1m']) || isset($data['reasoningPer1m'])
                ? (float) ($data['reasoning_per_1m'] ?? $data['reasoningPer1m'])
                : null,
            currency: $data['currency'] ?? 'USD',
        );
    }

    public function toArray(): array
    {
        return [
            'input_per_1m' => $this->inputPer1m,
            'output_per_1m' => $this->outputPer1m,
            'reasoning_per_1m' => $this->reasoningPer1m,
            'currency' => $this->currency,
        ];
    }
}
