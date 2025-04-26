<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText;

use ElSchneider\StatamicAutoAltText\Commands\GenerateAltTextCommand;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Facades\AutoAltText as AutoAltTextFacade;
use ElSchneider\StatamicAutoAltText\FieldActions\GenerateAltTextAction;
use ElSchneider\StatamicAutoAltText\Services\CaptionServiceFactory;
use ElSchneider\StatamicAutoAltText\Services\MoondreamService;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

final class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        GenerateAltTextCommand::class,
    ];

    protected $actions = [
        GenerateAltTextAction::class,
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.ts',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/auto-alt-text.php', 'auto-alt-text');

        $this->app->singleton(CaptionServiceFactory::class);
        $this->app->bind(CaptionService::class, function ($app) {
            return $app->make(CaptionServiceFactory::class)->make();
        });
        $this->app->bind(MoondreamService::class);

        $this->app->singleton('auto-alt-text', function ($app) {
            return $app->make(StatamicAutoAltText::class);
        });

        $this->app->alias('auto-alt-text', AutoAltTextFacade::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/auto-alt-text.php' => config_path('auto-alt-text.php'),
            ], 'auto-alt-text-config');
        }

        if ($this->app->runningInConsole()) {
            // Assets are published via Vite manifest, this might be redundant
            // $this->publishes([
            //     __DIR__.'/../resources/dist' => public_path('vendor/statamic-auto-alt-text'),
            // ], 'auto-alt-text-assets');
        }

        // JS registration handled by $vite property
        // Statamic::script('statamic-auto-alt-text', 'addon');

        // Load translations or other resources if needed
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'statamic-auto-alt-text');

        // The spec mentions registering field actions via JS, so no PHP registration here.
        // Statamic::afterInstalled(function () {
        //    // Register field action using JS
        // });
    }
}
