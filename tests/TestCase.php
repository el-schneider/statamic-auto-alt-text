<?php

declare(strict_types=1);

namespace Tests;

use ElSchneider\StatamicAutoAltText\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
    }
}
