<?php

declare(strict_types=1);

it('can instantiate the service provider', function () {
    $serviceProvider = app()->getProvider(ElSchneider\StatamicAutoAltText\ServiceProvider::class);

    expect($serviceProvider)->not->toBeNull();
});
