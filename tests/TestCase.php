<?php

namespace ElSchneider\StatamicAutoAltText\Tests;

use ElSchneider\StatamicAutoAltText\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
