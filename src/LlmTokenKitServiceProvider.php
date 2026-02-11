<?php

namespace Frolax\LlmTokenKit;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Frolax\LlmTokenKit\Commands\LlmTokenKitCommand;

class LlmTokenKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-llm-tokenkit')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_llm_tokenkit_table')
            ->hasCommand(LlmTokenKitCommand::class);
    }
}
