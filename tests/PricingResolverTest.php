<?php

declare(strict_types=1);

use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\Pricing;
use Frolax\LlmTokenKit\Pricing\PricingResolver;

beforeEach(function () {
    $this->resolver = new PricingResolver;
});

describe('pricing match precedence', function () {
    it('prefers exact model match', function () {
        config()->set('llm-tokenkit.pricing.providers.openai.models.gpt-4o', [
            'input_per_1m' => 2.50,
            'output_per_1m' => 10.00,
            'currency' => 'USD',
        ]);
        config()->set('llm-tokenkit.pricing.providers.openai.models.gpt-4*', [
            'input_per_1m' => 5.00,
            'output_per_1m' => 20.00,
            'currency' => 'USD',
        ]);
        config()->set('llm-tokenkit.pricing.providers.openai.default', [
            'input_per_1m' => 1.00,
            'output_per_1m' => 4.00,
            'currency' => 'USD',
        ]);

        $model = new ModelRef(provider: 'openai', model: 'gpt-4o');
        $pricing = $this->resolver->resolve($model);

        expect($pricing->inputPer1m)->toBe(2.50);
        expect($pricing->outputPer1m)->toBe(10.00);
    });

    it('falls back to wildcard match', function () {
        config()->set('llm-tokenkit.pricing.providers.openai.models', [
            'gpt-4.1*' => [
                'input_per_1m' => 2.00,
                'output_per_1m' => 8.00,
                'currency' => 'USD',
            ],
        ]);
        config()->set('llm-tokenkit.pricing.providers.openai.default', [
            'input_per_1m' => 99.00,
            'output_per_1m' => 99.00,
            'currency' => 'USD',
        ]);

        $model = new ModelRef(provider: 'openai', model: 'gpt-4.1-mini');
        $pricing = $this->resolver->resolve($model);

        expect($pricing->inputPer1m)->toBe(2.00);
        expect($pricing->outputPer1m)->toBe(8.00);
    });

    it('falls back to provider default', function () {
        config()->set('llm-tokenkit.pricing.providers.anthropic.models', []);
        config()->set('llm-tokenkit.pricing.providers.anthropic.default', [
            'input_per_1m' => 3.00,
            'output_per_1m' => 15.00,
            'currency' => 'USD',
        ]);

        $model = new ModelRef(provider: 'anthropic', model: 'claude-unknown');
        $pricing = $this->resolver->resolve($model);

        expect($pricing->inputPer1m)->toBe(3.00);
        expect($pricing->outputPer1m)->toBe(15.00);
    });

    it('falls back to global default', function () {
        config()->set('llm-tokenkit.pricing.providers', []);
        config()->set('llm-tokenkit.pricing.default', [
            'input_per_1m' => 1.00,
            'output_per_1m' => 2.00,
            'currency' => 'USD',
        ]);

        $model = new ModelRef(provider: 'some-provider', model: 'some-model');
        $pricing = $this->resolver->resolve($model);

        expect($pricing->inputPer1m)->toBe(1.00);
        expect($pricing->outputPer1m)->toBe(2.00);
    });

    it('uses override pricing when provided', function () {
        $override = new Pricing(inputPer1m: 99.99, outputPer1m: 199.99, currency: 'EUR');
        $model = new ModelRef(provider: 'openai', model: 'gpt-4o');

        $pricing = $this->resolver->resolve($model, $override);

        expect($pricing->inputPer1m)->toBe(99.99);
        expect($pricing->outputPer1m)->toBe(199.99);
        expect($pricing->currency)->toBe('EUR');
    });
});
