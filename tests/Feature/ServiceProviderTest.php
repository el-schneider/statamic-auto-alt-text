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

    Statamic::provideToScript([
        'autoAltText' => [
            'enabledFields' => config('statamic.auto-alt-text.action_enabled_fields', ['alt', 'alt_text', 'alternative_text']),
            'timeout' => config('statamic.auto-alt-text.timeout', 60),
        ],
    ]);

    $jsonVars = Statamic::jsonVariables(Request::create('/'));

    expect($jsonVars['autoAltText']['timeout'])->toBe(120);
});
