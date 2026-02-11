<?php

namespace Frolax\LlmTokenKit\Tests;

use Frolax\LlmTokenKit\LlmTokenKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LlmTokenKitServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // No database needed for this package
    }
}
