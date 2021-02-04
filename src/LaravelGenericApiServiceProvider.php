<?php

namespace TaNteE\LaravelGenericApi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TaNteE\LaravelGenericApi\Commands\LaravelGenericApiCommand;

class LaravelGenericApiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name('laravel-generic-api');
    }
}
