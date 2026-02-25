<?php

declare(strict_types=1);

uses()->group('browser');

beforeEach(function () {
    $this->createAssetContainer('test_assets', 'Test Assets', 'assets');
    $this->asset = $this->createTestAsset();
});

it('displays generate alt text action on asset editor', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/";

    visit($url)
        ->waitForText($this->asset->basename())
        ->click("[value$='{$this->asset->basename()}']")
        ->click('Generate Alt Text')
        ->assertSee('Run action');
});

it('display field action on edit page', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/{$this->asset->basename()}/edit";

    visit($url)
        ->waitForText('Alt Text')
        ->click('.quick-list button')
        ->assertSee('Generate Alt Text');
});
