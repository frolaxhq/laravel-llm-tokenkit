<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit;

use Frolax\LlmTokenKit\Commands\LlmTokenKitCommand;
use Frolax\LlmTokenKit\Context\ContextBuilder;
use Frolax\LlmTokenKit\Contracts\TokenEstimatorInterface;
use Frolax\LlmTokenKit\Estimators\EstimatorChain;
use Frolax\LlmTokenKit\Estimators\HeuristicEstimator;
use Frolax\LlmTokenKit\Pricing\CostCalculator;
use Frolax\LlmTokenKit\Pricing\PricingResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LlmTokenKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('llm-tokenkit')
            ->hasConfigFile()
            ->hasCommand(LlmTokenKitCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register the heuristic estimator
        $this->app->singleton(HeuristicEstimator::class, function ($app) {
            return new HeuristicEstimator(
                config: $app['config']->get('llm-tokenkit.estimation', []),
            );
        });

        // Register the estimator chain with heuristic as default
        $this->app->singleton(EstimatorChain::class, function ($app) {
            $chain = new EstimatorChain(
                $app->make(HeuristicEstimator::class),
            );

            return $chain;
        });

        // Bind the interface to the chain for type-hinting
        $this->app->bind(TokenEstimatorInterface::class, function ($app) {
            return $app->make(HeuristicEstimator::class);
        });

        // Register pricing resolver
        $this->app->singleton(PricingResolver::class);

        // Register cost calculator
        $this->app->singleton(CostCalculator::class, function ($app) {
            return new CostCalculator(
                roundingPrecision: (int) config('llm-tokenkit.pricing.rounding_precision', 6),
            );
        });

        // Register context builder
        $this->app->singleton(ContextBuilder::class, function ($app) {
            return new ContextBuilder(
                estimatorChain: $app->make(EstimatorChain::class),
            );
        });

        // Register the main TokenKit manager as singleton
        $this->app->singleton(LlmTokenKit::class, function ($app) {
            return new LlmTokenKit(
                estimatorChain: $app->make(EstimatorChain::class),
                costCalculator: $app->make(CostCalculator::class),
                pricingResolver: $app->make(PricingResolver::class),
                contextBuilder: $app->make(ContextBuilder::class),
            );
        });
    }
}
