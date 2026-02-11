<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Enums;

enum EstimationMethod: string
{
    case Tokenizer = 'tokenizer';
    case Heuristic = 'heuristic';
}
