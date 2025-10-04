<?php

declare(strict_types=1);

namespace Tests;

use ElSchneider\StatamicAutoAltText\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
// use RuntimeException;
use RuntimeException;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use RefreshDatabase;
    use StatamicTestHelpers;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {

        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
    }
}
