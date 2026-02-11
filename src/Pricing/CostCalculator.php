<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Pricing;

use Frolax\LlmTokenKit\Data\CostBreakdown;
use Frolax\LlmTokenKit\Data\Pricing;
use Frolax\LlmTokenKit\Data\TokenUsage;

class CostCalculator
{
    public function __construct(
        private readonly int $roundingPrecision = 6,
    ) {}

    /**
     * Calculate cost breakdown from token usage and pricing.
     */
    public function calculate(TokenUsage $usage, Pricing $pricing): CostBreakdown
    {
        $precision = $this->roundingPrecision;

        $inputCost = round(
            ($usage->promptTokens / 1_000_000) * $pricing->inputPer1m,
            $precision,
        );

        $outputCost = round(
            ($usage->completionTokens / 1_000_000) * $pricing->outputPer1m,
            $precision,
        );

        $reasoningCost = null;
        if ($usage->reasoningTokens !== null && $pricing->reasoningPer1m !== null) {
            $reasoningCost = round(
                ($usage->reasoningTokens / 1_000_000) * $pricing->reasoningPer1m,
                $precision,
            );
        }

        $totalCost = round(
            $inputCost + $outputCost + ($reasoningCost ?? 0.0),
            $precision,
        );

        return new CostBreakdown(
            inputCost: $inputCost,
            outputCost: $outputCost,
            reasoningCost: $reasoningCost,
            totalCost: $totalCost,
            currency: $pricing->currency,
        );
    }
}
