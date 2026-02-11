<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Enums;

enum ToolMessagePolicy: string
{
    case Exclude = 'exclude';
    case SummarizeOnly = 'summarize_only';
    case IncludeSmall = 'include_small';
}
