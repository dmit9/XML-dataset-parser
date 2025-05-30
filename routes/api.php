<?php

use App\Http\Controllers\CatalogImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogController;


Route::post('/imports', [CatalogImportController::class, 'store']);
Route::get('/imports/{id}', [CatalogImportController::class, 'show']);
Route::get('/imports/{id}/products', [CatalogImportController::class, 'products']);
Route::get('/catalog/products', [CatalogController::class, 'products']);
Route::get('/catalog/filters', [CatalogController::class, 'filters']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
