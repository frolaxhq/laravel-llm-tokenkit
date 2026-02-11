<?php

declare(strict_types=1);

namespace Frolax\LlmTokenKit\Data;

class ContextBuildResult
{
    public function __construct(
        public readonly array $messages,
        public readonly TokenEstimate $tokenEstimate,
        public readonly bool $wasTruncated = false,
        public readonly int $droppedMessageCount = 0,
        public readonly array $warnings = [],
    ) {}

    public function toArray(): array
    {
        return [
            'messages' => $this->messages,
            'token_estimate' => $this->tokenEstimate->toArray(),
            'was_truncated' => $this->wasTruncated,
            'dropped_message_count' => $this->droppedMessageCount,
            'warnings' => $this->warnings,
        ];
    }
}
