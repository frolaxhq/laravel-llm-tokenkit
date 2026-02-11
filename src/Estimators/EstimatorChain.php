<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Estimators;

use Frolax\LlmTokenKit\Contracts\TokenEstimatorInterface;
use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\TokenEstimate;
use Frolax\LlmTokenKit\Enums\Confidence;

class EstimatorChain
{
    /** @var TokenEstimatorInterface[] */
    private array $estimators = [];

    public function __construct(TokenEstimatorInterface ...$estimators)
    {
        $this->estimators = $estimators;
    }

    /**
     * Register an estimator to the chain.
     */
    public function register(TokenEstimatorInterface $estimator): self
    {
        $this->estimators[] = $estimator;

        return $this;
    }

    /**
     * Pick the best estimator that supports the model (highest confidence).
     */
    public function resolve(ModelRef $model): TokenEstimatorInterface
    {
        $best = null;
        $bestConfidence = null;

        foreach ($this->estimators as $estimator) {
            if (! $estimator->supports($model)) {
                continue;
            }

            // Use a short test string to gauge confidence
            $probe = $estimator->estimateText('test', $model);
            $confidence = $probe->confidence;

            if ($best === null || $confidence->isAtLeast($bestConfidence ?? Confidence::Low)) {
                $best = $estimator;
                $bestConfidence = $confidence;
            }
        }

        if ($best === null) {
            throw new \RuntimeException(
                sprintf('No estimator supports model "%s/%s"', $model->provider, $model->model)
            );
        }

        return $best;
    }

    /**
     * Estimate tokens for text using the best available estimator.
     */
    public function estimateText(string $text, ModelRef $model, array $opts = []): TokenEstimate
    {
        return $this->resolve($model)->estimateText($text, $model, $opts);
    }

    /**
     * Estimate tokens for messages using the best available estimator.
     */
    public function estimateMessages(array $messages, ModelRef $model, array $opts = []): TokenEstimate
    {
        return $this->resolve($model)->estimateMessages($messages, $model, $opts);
    }

    /**
     * Get all registered estimator names.
     *
     * @return string[]
     */
    public function registeredEstimators(): array
    {
        return array_map(fn (TokenEstimatorInterface $e) => $e->name(), $this->estimators);
    }

    /**
     * Get all estimators that support a given model.
     *
     * @return TokenEstimatorInterface[]
     */
    public function supportedEstimators(ModelRef $model): array
    {
        return array_values(
            array_filter($this->estimators, fn (TokenEstimatorInterface $e) => $e->supports($model))
        );
    }
}
