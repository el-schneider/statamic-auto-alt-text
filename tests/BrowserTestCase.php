<?php

declare(strict_types=1);

namespace Tests;

abstract class BrowserTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useWritablePublicPath();
        $this->useWritableResourcePath();
        $this->useWritableBlueprintDirectory();
        $this->fixFakeStacheDirectory();
        $this->withVite();
        $this->setupStatamicAssets();
        $this->actingAs($this->createTestUser());
    }

    protected function fixFakeStacheDirectory(): void
    {
        $this->fakeStacheDirectory = dirname(__DIR__).'/tests/__fixtures__/dev-null-browser';

        $directories = [
            '',
            'users',
            'content/collections',
            'content/taxonomies',
            'content/navigation',
            'content/globals',
            'content/assets',
            'content/structures/navigation',
            'content/structures/collections',
            'content/submissions',
        ];

        foreach ($directories as $directory) {
            $path = $directory === '' ? $this->fakeStacheDirectory : $this->fakeStacheDirectory.'/'.$directory;

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $this->preventSavingStacheItemsToDisk();
    }

    protected function useWritablePublicPath(): void
    {
        $publicPath = base_path('tests/__fixtures__/public');

        if (! is_dir($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        $this->app->usePublicPath($publicPath);

        // Reconfigure the public disk to use the writable public path
        config(['filesystems.disks.public' => [
            'driver' => 'local',
            'root' => $publicPath,
            'url' => '',
            'visibility' => 'public',
        ]]);

        // Configure the assets disk
        $assetsPath = $publicPath.'/assets';
        if (! is_dir($assetsPath)) {
            mkdir($assetsPath, 0755, true);
        }

        config(['filesystems.disks.assets' => [
            'driver' => 'local',
            'root' => $assetsPath,
            'url' => '/assets',
            'visibility' => 'public',
        ]]);
    }

    protected function useWritableResourcePath(): void
    {
        $resourcePath = base_path('tests/__fixtures__/resources');

        if (! is_dir($resourcePath)) {
            mkdir($resourcePath, 0755, true);
        }

        $this->app->instance('path.resources', $resourcePath);
    }

    protected function useWritableBlueprintDirectory(): void
    {
        $blueprintDirectory = base_path('tests/__fixtures__/resources/blueprints');

        if (! is_dir($blueprintDirectory)) {
            mkdir($blueprintDirectory, 0755, true);
        }

        \Statamic\Facades\Blueprint::setDirectory($blueprintDirectory);
    }

    protected function setupStatamicAssets(): void
    {
        $addonRoot = dirname(__DIR__);
        $testbenchPublic = public_path();

        // Statamic CP assets
        $vendorStatamicDir = $testbenchPublic.'/vendor/statamic/cp';
        if (! is_dir($vendorStatamicDir)) {
            mkdir($vendorStatamicDir, 0755, true);
        }

        $statamicBuildSource = $addonRoot.'/vendor/statamic/cms/resources/dist/build';
        $statamicBuildDestination = $vendorStatamicDir.'/build';
        if (! file_exists($statamicBuildDestination) && is_dir($statamicBuildSource)) {
            symlink($statamicBuildSource, $statamicBuildDestination);
        }

        // Addon assets
        $addonAssetsSource = $addonRoot.'/resources/dist';
        $addonAssetsDestination = $testbenchPublic.'/vendor/statamic-auto-alt-text';
        if (! file_exists($addonAssetsDestination) && is_dir($addonAssetsSource)) {
            symlink($addonAssetsSource, $addonAssetsDestination);
        }
    }
}
