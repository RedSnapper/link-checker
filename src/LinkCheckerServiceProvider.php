<?php

namespace RedSnapper\LinkChecker;

use Illuminate\Support\ServiceProvider;
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;

class LinkCheckerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/link-checker.php' => config_path('link-checker.php'),
            ], 'link-checker-config');;
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/link-checker.php', 'link-checker');

        $this->app->singleton(LinkCheckerInterface::class, function ($app) {
            // Here, you explicitly pass the config to the Checker's constructor
            return new UrlChecker(config('link-checker'));
        });

        $this->app->alias(LinkCheckerInterface::class, 'link-checker');

    }
}
