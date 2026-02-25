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

    protected function tearDown(): void
    {
        if (isset($this->fakeStacheDirectory) && is_string($this->fakeStacheDirectory)) {
            app('files')->deleteDirectory($this->fakeStacheDirectory);
            if (! is_dir($this->fakeStacheDirectory)) {
                mkdir($this->fakeStacheDirectory, 0755, true);
            }
            touch($this->fakeStacheDirectory.'/.gitkeep');
        }

        parent::tearDown();
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
    }
}
