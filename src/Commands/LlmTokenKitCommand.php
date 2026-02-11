<?php

namespace Frolax\LlmTokenKit\Commands;

use Illuminate\Console\Command;

class LlmTokenKitCommand extends Command
{
    public $signature = 'laravel-llm-tokenkit';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
