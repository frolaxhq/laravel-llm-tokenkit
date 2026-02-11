<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Enums;

enum ContextStrategy: string
{
    case RollingWindow = 'rolling_window';
    case TruncateByTokens = 'truncate_by_tokens';
}
