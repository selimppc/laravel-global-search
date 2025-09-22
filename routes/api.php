<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->get('/global-search', \Selimppc\GlobalSearch\Http\Controllers\GlobalSearchController::class);
