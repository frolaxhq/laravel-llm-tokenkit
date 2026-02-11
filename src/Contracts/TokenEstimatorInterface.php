<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Contracts;

use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\TokenEstimate;

interface TokenEstimatorInterface
{
    /**
     * Whether this estimator supports the given model.
     */
    public function supports(ModelRef $model): bool;

    /**
     * Estimate token count for a plain text string.
     */
    public function estimateText(string $text, ModelRef $model, array $opts = []): TokenEstimate;

    /**
     * Estimate token count for an array of chat messages.
     * Each message: ['role' => string, 'content' => string]
     */
    public function estimateMessages(array $messages, ModelRef $model, array $opts = []): TokenEstimate;

    /**
     * Human-readable name.
     */
    public function name(): string;
}
