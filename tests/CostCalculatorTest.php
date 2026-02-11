<?php

declare(strict_types=1);

use Frolax\LlmTokenKit\Data\Pricing;
use Frolax\LlmTokenKit\Data\TokenUsage;
use Frolax\LlmTokenKit\Pricing\CostCalculator;

beforeEach(function () {
    $this->calculator = new CostCalculator(roundingPrecision: 6);
});

describe('cost calculation', function () {
    it('calculates correct costs with rounding', function () {
        $usage = new TokenUsage(promptTokens: 1500, completionTokens: 500);
        $pricing = new Pricing(inputPer1m: 2.50, outputPer1m: 10.00);

        $result = $this->calculator->calculate($usage, $pricing);

        // Input: 1500/1000000 * 2.50 = 0.00375
        expect($result->inputCost)->toBe(0.00375);

        // Output: 500/1000000 * 10.00 = 0.005
        expect($result->outputCost)->toBe(0.005);

        // Total
        expect($result->totalCost)->toBe(0.00875);
        expect($result->reasoningCost)->toBeNull();
        expect($result->currency)->toBe('USD');
    });

    it('includes reasoning cost when available', function () {
        $usage = new TokenUsage(promptTokens: 1000, completionTokens: 200, reasoningTokens: 5000);
        $pricing = new Pricing(inputPer1m: 15.00, outputPer1m: 60.00, reasoningPer1m: 60.00);

        $result = $this->calculator->calculate($usage, $pricing);

        // Input: 1000/1M * 15 = 0.015
        expect($result->inputCost)->toBe(0.015);

        // Output: 200/1M * 60 = 0.012
        expect($result->outputCost)->toBe(0.012);

        // Reasoning: 5000/1M * 60 = 0.3
        expect($result->reasoningCost)->toBe(0.3);

        // Total: 0.015 + 0.012 + 0.3 = 0.327
        expect($result->totalCost)->toBe(0.327);
    });

    it('rounds to configured precision', function () {
        $calculator = new CostCalculator(roundingPrecision: 4);
        $usage = new TokenUsage(promptTokens: 333, completionTokens: 777);
        $pricing = new Pricing(inputPer1m: 3.33, outputPer1m: 7.77);

        $result = $calculator->calculate($usage, $pricing);

        // Input: 333/1M * 3.33 = 0.001108890... → 0.0011
        expect($result->inputCost)->toBe(0.0011);

        // Output: 777/1M * 7.77 = 0.006037290... → 0.006
        expect($result->outputCost)->toBe(0.006);
    });

    it('handles zero usage', function () {
        $usage = new TokenUsage(promptTokens: 0, completionTokens: 0);
        $pricing = new Pricing(inputPer1m: 10.00, outputPer1m: 30.00);

        $result = $this->calculator->calculate($usage, $pricing);

        expect($result->inputCost)->toBe(0.0);
        expect($result->outputCost)->toBe(0.0);
        expect($result->totalCost)->toBe(0.0);
    });

    it('preserves currency from pricing', function () {
        $usage = new TokenUsage(promptTokens: 100, completionTokens: 100);
        $pricing = new Pricing(inputPer1m: 1.00, outputPer1m: 2.00, currency: 'EUR');

        $result = $this->calculator->calculate($usage, $pricing);

        expect($result->currency)->toBe('EUR');
    });
});

describe('token usage', function () {
    it('calculates total tokens', function () {
        $usage = new TokenUsage(promptTokens: 100, completionTokens: 50, reasoningTokens: 200);

        expect($usage->totalTokens())->toBe(350);
    });

    it('creates from array', function () {
        $usage = TokenUsage::fromArray([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'reasoning_tokens' => 200,
        ]);

        expect($usage->promptTokens)->toBe(100);
        expect($usage->completionTokens)->toBe(50);
        expect($usage->reasoningTokens)->toBe(200);
    });
});
