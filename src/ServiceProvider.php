<?php

namespace ElSchneider\StatamicAutoAltText;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.ts',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon()
    {
        //
    }
}
