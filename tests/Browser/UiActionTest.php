<?php

declare(strict_types=1);

uses()->group('browser');

beforeEach(function () {
    $this->createAssetContainer('test_assets', 'Test Assets', 'assets');
    $this->asset = $this->createTestAsset();
});

it('display field action on edit page', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/{$this->asset->basename()}/edit";

    // Statamic 5 renders field actions in a quick-list dropdown; the
    // data-ui-dropdown-trigger primitive only exists from Statamic 6 on.
    visit($url)
        ->waitForText('Alt Text')
        ->click('.text-fieldtype .quick-list button')
        ->assertSee('Generate Alt Text');
});
