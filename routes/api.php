<?php

use App\Http\Controllers\CatalogImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/imports', [CatalogImportController::class, 'store']);
Route::get('/imports/{id}', [CatalogImportController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
