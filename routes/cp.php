<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Http\Controllers\GenerateAltTextController;
use Illuminate\Support\Facades\Route;

Route::name('auto-alt-text.')->prefix('auto-alt-text')->group(function () {
    Route::post('generate', [GenerateAltTextController::class, 'trigger'])->name('trigger');
    Route::get('check', [GenerateAltTextController::class, 'check'])->name('check');
});
