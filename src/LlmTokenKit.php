<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit;

use Frolax\LlmTokenKit\Context\ContextBuilder;
use Frolax\LlmTokenKit\Data\ContextBuildRequest;
use Frolax\LlmTokenKit\Data\ContextBuildResult;
use Frolax\LlmTokenKit\Data\CostBreakdown;
use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\Pricing;
use Frolax\LlmTokenKit\Data\TokenEstimate;
use Frolax\LlmTokenKit\Data\TokenUsage;
use Frolax\LlmTokenKit\Estimators\EstimatorChain;
use Frolax\LlmTokenKit\Pricing\CostCalculator;
use Frolax\LlmTokenKit\Pricing\PricingResolver;

class LlmTokenKit
{
    public function __construct(
        private readonly EstimatorChain $estimatorChain,
        private readonly CostCalculator $costCalculator,
        private readonly PricingResolver $pricingResolver,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    /**
     * Estimate token count for a plain text string.
     */
    public function estimateText(string $text, ModelRef|array $model, array $opts = []): TokenEstimate
    {
        $modelRef = $this->normalizeModel($model);

        return $this->estimatorChain->estimateText($text, $modelRef, $opts);
    }

    /**
     * Estimate token count for an array of chat messages.
     */
    public function estimateMessages(array $messages, ModelRef|array $model, array $opts = []): TokenEstimate
    {
        $modelRef = $this->normalizeModel($model);

        return $this->estimatorChain->estimateMessages($messages, $modelRef, $opts);
    }

    /**
     * Calculate cost breakdown from usage and pricing.
     */
    public function cost(
        TokenUsage|array $usage,
        Pricing|array|null $pricing = null,
        ModelRef|array|null $model = null,
    ): CostBreakdown {
        $tokenUsage = $usage instanceof TokenUsage ? $usage : TokenUsage::fromArray($usage);
        $pricingOverride = $pricing instanceof Pricing ? $pricing : ($pricing !== null ? Pricing::fromArray($pricing) : null);

        // Resolve pricing from config if not explicitly provided
        $modelRef = $model !== null ? $this->normalizeModel($model) : new ModelRef(provider: 'unknown', model: 'unknown');
        $resolvedPricing = $this->pricingResolver->resolve($modelRef, $pricingOverride);

        return $this->costCalculator->calculate($tokenUsage, $resolvedPricing);
    }

    /**
     * Build context messages from a request.
     */
    public function buildContext(ContextBuildRequest|array $req): ContextBuildResult
    {
        $request = $req instanceof ContextBuildRequest ? $req : ContextBuildRequest::fromArray($req);

        return $this->contextBuilder->build($request);
    }

    /**
     * Get the underlying estimator chain (for inspection/testing).
     */
    public function estimatorChain(): EstimatorChain
    {
        return $this->estimatorChain;
    }

    /**
     * Normalize a model reference from array or DTO.
     */
    private function normalizeModel(ModelRef|array $model): ModelRef
    {
        return $model instanceof ModelRef ? $model : ModelRef::fromArray($model);
    }
}
