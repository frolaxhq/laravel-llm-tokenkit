<?php

namespace Frolax\LlmTokenKit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Frolax\LlmTokenKit\LlmTokenKit
 */
class LlmTokenKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Frolax\LlmTokenKit\LlmTokenKit::class;
    }
}
