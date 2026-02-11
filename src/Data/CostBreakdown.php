<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

class CostBreakdown
{
    public function __construct(
        public readonly float $inputCost,
        public readonly float $outputCost,
        public readonly ?float $reasoningCost,
        public readonly float $totalCost,
        public readonly string $currency = 'USD',
    ) {}

    public function toArray(): array
    {
        return [
            'input_cost' => $this->inputCost,
            'output_cost' => $this->outputCost,
            'reasoning_cost' => $this->reasoningCost,
            'total_cost' => $this->totalCost,
            'currency' => $this->currency,
        ];
    }
}
