<?php

it('can instantiate the service provider', function () {
    $serviceProvider = app()->getProvider(\ElSchneider\StatamicAutoAltText\ServiceProvider::class);

    expect($serviceProvider)->not->toBeNull();
});
