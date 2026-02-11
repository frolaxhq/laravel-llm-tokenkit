<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Facades;

use Frolax\LlmTokenKit\Data\ContextBuildRequest;
use Frolax\LlmTokenKit\Data\ContextBuildResult;
use Frolax\LlmTokenKit\Data\CostBreakdown;
use Frolax\LlmTokenKit\Data\ModelRef;
use Frolax\LlmTokenKit\Data\Pricing;
use Frolax\LlmTokenKit\Data\TokenEstimate;
use Frolax\LlmTokenKit\Data\TokenUsage;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TokenEstimate estimateText(string $text, ModelRef|array $model, array $opts = [])
 * @method static TokenEstimate estimateMessages(array $messages, ModelRef|array $model, array $opts = [])
 * @method static CostBreakdown cost(TokenUsage|array $usage, Pricing|array|null $pricing = null, ModelRef|array|null $model = null)
 * @method static ContextBuildResult buildContext(ContextBuildRequest|array $req)
 *
 * @see \Frolax\LlmTokenKit\LlmTokenKit
 */
class LlmTokenKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Frolax\LlmTokenKit\LlmTokenKit::class;
    }
}
