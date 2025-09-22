<?php

use Illuminate\Support\Facades\Route;
use LaravelGlobalSearch\GlobalSearch\Http\Controllers\GlobalSearchController;

/*
|--------------------------------------------------------------------------
| Global Search API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the GlobalSearchServiceProvider and are
| automatically registered when the package is installed.
|
*/

Route::get('/global-search', GlobalSearchController::class)->name('global-search.search');
