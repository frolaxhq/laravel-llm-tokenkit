<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Pricing;

use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\Pricing;

class PricingResolver
{
    /**
     * Resolve pricing for a model with precedence:
     * 1. Exact model match
     * 2. Wildcard/prefix match
     * 3. Provider default
     * 4. Global default
     */
    public function resolve(ModelRef $model, ?Pricing $override = null): Pricing
    {
        if ($override !== null) {
            return $override;
        }

        $provider = $model->provider;
        $modelName = $model->model;

        // 1. Exact model match
        $exact = config("llm-tokenkit.pricing.providers.{$provider}.models.{$modelName}");
        if ($exact) {
            return Pricing::fromArray($exact);
        }

        // 2. Wildcard/prefix match
        $providerModels = config("llm-tokenkit.pricing.providers.{$provider}.models", []);
        foreach ($providerModels as $pattern => $pricing) {
            if ($this->matchesWildcard($pattern, $modelName)) {
                return Pricing::fromArray($pricing);
            }
        }

        // 3. Provider default
        $providerDefault = config("llm-tokenkit.pricing.providers.{$provider}.default");
        if ($providerDefault) {
            return Pricing::fromArray($providerDefault);
        }

        // 4. Global default
        $globalDefault = config('llm-tokenkit.pricing.default', [
            'input_per_1m' => 1.0,
            'output_per_1m' => 2.0,
            'currency' => 'USD',
        ]);

        return Pricing::fromArray($globalDefault);
    }

    /**
     * Check if a pattern matches a model name using wildcard (* suffix).
     */
    private function matchesWildcard(string $pattern, string $modelName): bool
    {
        // Skip non-wildcard patterns (already handled by exact match)
        if (! str_contains($pattern, '*')) {
            return false;
        }

        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $modelName);
    }
}
