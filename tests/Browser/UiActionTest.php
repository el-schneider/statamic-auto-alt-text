<?php

declare(strict_types=1);

beforeEach(function () {
    $this->asset = $this->createTestAsset();
});

it('displays generate alt text action on asset editor', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/";

    visit($url)
        ->click("[aria-label$='{$this->asset->basename()}']")
        ->assertSee('Generate Alt Text');
});

it('display field action on edit page', function () {
    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/{$this->asset->basename()}/edit";

    visit($url)
        ->click('.quick-list button')
        ->assertSee('Generate Alt Text');
});
