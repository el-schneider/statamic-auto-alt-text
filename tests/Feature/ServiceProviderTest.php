<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Statamic;

it('can instantiate the service provider', function () {
    $serviceProvider = app()->getProvider(ElSchneider\StatamicAutoAltText\ServiceProvider::class);

    expect($serviceProvider)->not->toBeNull();
});

it('exposes timeout to frontend script data', function () {
    $jsonVars = Statamic::jsonVariables(Request::create('/'));

    expect($jsonVars)->toHaveKey('autoAltText')
        ->and($jsonVars['autoAltText'])->toHaveKey('timeout')
        ->and($jsonVars['autoAltText']['timeout'])->toBe(60);
});

it('exposes custom timeout to frontend script data', function () {
    config(['statamic.auto-alt-text.timeout' => 120]);

    // Re-boot the service provider so it re-registers script data with the new config
    $provider = app()->getProvider(ElSchneider\StatamicAutoAltText\ServiceProvider::class);
    $provider->boot();

    $jsonVars = Statamic::jsonVariables(Request::create('/'));

    expect($jsonVars['autoAltText']['timeout'])->toBe(120);
});
