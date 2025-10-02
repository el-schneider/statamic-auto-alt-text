<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as AssetContainerModel;
use Statamic\Auth\User as UserModel;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;

trait StatamicTestHelpers
{
    protected UserModel $testUser;

    protected AssetContainerModel $testAssetContainer;

    protected Asset $testAsset;

    protected function createTestUser(): UserModel
    {
        $this->testUser = User::make()
            ->email('test@example.com')
            ->id('test-user')
            ->set('name', 'Test User')
            ->set('super', true)
            ->password('password');

        $this->testUser->save();

        return $this->testUser;
    }

    protected function createAssetContainer(string $handle = 'test_assets', string $title = 'Test Assets'): AssetContainerModel
    {
        $this->testAssetContainer = AssetContainer::make($handle);
        $this->testAssetContainer->title($title);
        $this->testAssetContainer->disk('public'); // Use 'public' disk instead of 'local'
        $this->testAssetContainer->save();

        return $this->testAssetContainer;
    }

    protected function createTestAsset(
        string $filename = 'test-image.jpg',
        ?AssetContainerModel $container = null
    ): Asset {
        if (! $container) {
            $container = $this->testAssetContainer ?? $this->createAssetContainer();
        }

        // Create the file on the storage disk
        $disk = Storage::disk($container->diskHandle());
        $disk->put($filename, 'fake image content');

        // Verify file was actually written
        if (! $disk->exists($filename)) {
            throw new Exception("Failed to create file on disk: {$filename}");
        }

        // Now create asset after file exists
        $this->testAsset = $container->makeAsset($filename);

        // Verify file exists before setting data
        if (! $this->testAsset->exists()) {
            throw new Exception("Asset file was not created properly: {$filename}");
        }

        $this->testAsset->data([
            'alt' => null, // Ensure alt text is null so our action appears
        ]);
        $this->testAsset->save();

        return $this->testAsset;
    }

    protected function loginUser(?UserModel $user = null): UserModel
    {
        $user ??= $this->testUser ?? $this->createTestUser();

        $this->actingAs($user, 'statamic');

        return $user;
    }
}
