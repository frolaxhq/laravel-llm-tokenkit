<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Enums;

enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
