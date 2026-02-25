<?php

declare(strict_types=1);

uses()->group('browser');

beforeEach(function () {
    $this->createAssetContainer('test_assets', 'Test Assets', 'assets');
    $this->asset = $this->createTestAsset();
});

it('display field action on edit page', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/{$this->asset->basename()}/edit";

    visit($url)
        ->waitForText('Alt Text')
        ->click('.text-fieldtype [data-ui-dropdown-trigger]')
        ->assertSee('Generate Alt Text');
});
