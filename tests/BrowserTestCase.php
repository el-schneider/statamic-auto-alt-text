<?php

declare(strict_types=1);

namespace Tests;

abstract class BrowserTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withVite();

        $this->setupStatamicAssets();

        $this->actingAs($this->createTestUser());
    }

    protected function setupStatamicAssets(): void
    {
        $addonRoot = dirname(__DIR__);
        $testbenchPublic = public_path();

        // Create vendor/statamic/cp directory in testbench public
        $vendorStatamicDir = $testbenchPublic.'/vendor/statamic/cp';
        if (! is_dir($vendorStatamicDir)) {
            mkdir($vendorStatamicDir, 0755, true);
        }

        $buildSource = $addonRoot.'/vendor/statamic/cms/resources/dist/build';

        // Symlink Statamic CP build
        $buildSymlink = $vendorStatamicDir.'/build';
        if (! file_exists($buildSymlink) && is_dir($buildSource)) {
            symlink($buildSource, $buildSymlink);
        }
    }
}
