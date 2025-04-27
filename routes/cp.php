<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Http\Controllers\GenerateAltTextController;
use Illuminate\Support\Facades\Route;

// Route group for CP actions under the addon's namespace
// Prefix matches the Statamic::script handle used in the Service Provider
Route::name('statamic-auto-alt-text.')->prefix('statamic-auto-alt-text')->group(function () {

    Route::post('generate', [GenerateAltTextController::class, 'trigger'])->name('trigger');

    Route::get('check', [GenerateAltTextController::class, 'check'])->name('check');

});
