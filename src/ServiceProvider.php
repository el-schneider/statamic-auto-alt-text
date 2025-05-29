<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText;

use ElSchneider\StatamicAutoAltText\Commands\GenerateAltTextCommand;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Facades\AutoAltText as AutoAltTextFacade;
use ElSchneider\StatamicAutoAltText\FieldActions\GenerateAltTextAction;
use ElSchneider\StatamicAutoAltText\Listeners\HandleAssetEvent;
use ElSchneider\StatamicAutoAltText\Services\CaptionServiceFactory;
use ElSchneider\StatamicAutoAltText\Services\MoondreamService;
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

    public function boot(): void
    {
        parent::boot();

        $this->mergeConfigFrom(__DIR__.'/../config/auto-alt-text.php', 'statamic.auto-alt-text');

        $this->publishes([
            __DIR__.'/../config/auto-alt-text.php' => config_path('statamic/auto-alt-text.php'),
        ], 'statamic-auto-alt-text-config');

        $this->app->singleton(CaptionServiceFactory::class);
        $this->app->singleton(CaptionService::class, function ($app) {
            return $app->make(CaptionServiceFactory::class)->make();
        });
        $this->app->bind(MoondreamService::class);

        $this->app->singleton('auto-alt-text', function ($app) {
            return $app->make(StatamicAutoAltText::class);
        });

        $this->app->alias('auto-alt-text', AutoAltTextFacade::class);

        StatamicGenerateAltTextAction::register();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'auto-alt-text');

        Statamic::provideToScript([
            'autoAltText' => [
                'enabledFields' => config('statamic.auto-alt-text.action_enabled_fields', ['alt', 'alt_text', 'alternative_text']),
            ],
        ]);

        $this->registerEventListeners();
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
