<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

use Frolax\LlmTokenKit\Enums\Confidence;
use Frolax\LlmTokenKit\Enums\EstimationMethod;

class TokenEstimate
{
    public function __construct(
        public readonly int $inputTokensEstimated,
        public readonly array $notes = [],
        public readonly Confidence $confidence = Confidence::Medium,
        public readonly EstimationMethod $method = EstimationMethod::Heuristic,
    ) {}

    public function toArray(): array
    {
        return [
            'input_tokens_estimated' => $this->inputTokensEstimated,
            'notes' => $this->notes,
            'confidence' => $this->confidence->value,
            'method' => $this->method->value,
        ];
    }
}
