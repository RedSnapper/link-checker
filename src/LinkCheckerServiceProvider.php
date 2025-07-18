<?php

namespace RedSnapper\LinkChecker;

use Aws\Lambda\LambdaClient;
use Illuminate\Support\ServiceProvider;
use RedSnapper\LinkChecker\Contracts\LinkCheckerInterface;
use RedSnapper\LinkChecker\Extractor\HtmlTitleExtractor;
use RedSnapper\LinkChecker\Extractor\PdfTitleExtractor;
use RedSnapper\LinkChecker\Extractor\TitleExtractorManager;

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


        // Check if the class is already registered before adding our default.
        if (! $this->app->bound(LambdaClient::class)) {

            $this->app->singleton(LambdaClient::class, function () {
                return new LambdaClient([
                    'version' => 'latest',
                    'region'  => config('link-checker.pdf.region'),
                ]);
            });
        }


        $this->app->singleton(TitleExtractorManager::class, function ($app)  {

            $extractors = [];

            // Always add the HTML extractor
            $extractors[] = new HtmlTitleExtractor();

            // Conditionally add the PDF extractor only if the ARN is configured
            $pdfConfig = config('link-checker.pdf');
            if (!empty($pdfConfig['lambda_arn'])) {

                $extractors[] = new PdfTitleExtractor($app->make(LambdaClient::class), $pdfConfig);
            }

            return new TitleExtractorManager($extractors);
        });

        $this->app->singleton(LinkCheckerInterface::class, function ($app) {
            // Here, you explicitly pass the config to the Checker's constructor
            return new UrlChecker($app->make(TitleExtractorManager::class),config('link-checker'));
        });

        $this->app->alias(LinkCheckerInterface::class, 'link-checker');

    }
}
