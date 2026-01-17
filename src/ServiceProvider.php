<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText;

use ElSchneider\StatamicAutoAltText\Commands\GenerateAltTextCommand;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Facades\AutoAltText as AutoAltTextFacade;
use ElSchneider\StatamicAutoAltText\Listeners\HandleAssetEvent;
use ElSchneider\StatamicAutoAltText\Services\AssetExclusionService;
use ElSchneider\StatamicAutoAltText\Services\ImageProcessor;
use ElSchneider\StatamicAutoAltText\Services\PrismCaptionService;
use ElSchneider\StatamicAutoAltText\StatamicActions\GenerateAltTextAction as StatamicGenerateAltTextAction;
use Illuminate\Support\Facades\Event;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

final class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        GenerateAltTextCommand::class,
    ];

    protected $actions = [
        StatamicGenerateAltTextAction::class,
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

    public function boot(): void
    {
        parent::boot();

        $this->registerConfiguration();
        $this->registerServices();
        $this->registerFacades();
        $this->registerTranslations();
        $this->registerScriptData();
        $this->registerEventListeners();
    }

    /**
     * Register configuration files and publish paths.
     */
    private function registerConfiguration(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auto-alt-text.php', 'statamic.auto-alt-text');

        $this->publishes([
            __DIR__.'/../config/auto-alt-text.php' => config_path('statamic/auto-alt-text.php'),
        ], 'statamic-auto-alt-text-config');
    }

    /**
     * Register all core services in the container.
     */
    private function registerServices(): void
    {
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(AssetExclusionService::class);

        $this->app->bind(CaptionService::class, function ($app) {
            return new PrismCaptionService(
                $app->make(ImageProcessor::class),
                config('statamic.auto-alt-text'),
            );
        });
    }

    /**
     * Register facade bindings and aliases.
     */
    private function registerFacades(): void
    {
        $this->app->singleton('auto-alt-text', function ($app) {
            return $app->make(StatamicAutoAltText::class);
        });

        $this->app->alias('auto-alt-text', AutoAltTextFacade::class);
    }

    /**
     * Register translation files.
     */
    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'auto-alt-text');
    }

    /**
     * Register data to be available in Statamic's Control Panel JavaScript.
     */
    private function registerScriptData(): void
    {
        Statamic::provideToScript([
            'autoAltText' => [
                'enabledFields' => config('statamic.auto-alt-text.action_enabled_fields', ['alt', 'alt_text', 'alternative_text']),
            ],
        ]);
    }

    /**
     * Register event listeners based on configuration.
     */
    private function registerEventListeners(): void
    {
        $eventsToListen = config('statamic.auto-alt-text.automatic_generation_events', []);

        if (! is_array($eventsToListen)) {
            return;
        }

        foreach ($eventsToListen as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, HandleAssetEvent::class);
            }
        }
    }
}
