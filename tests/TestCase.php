<?php

namespace Frolax\LlmTokenKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Frolax\LlmTokenKit\LlmTokenKitServiceProvider;

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
