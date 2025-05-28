<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestImportController;

Route::get('/test-import/start', [TestImportController::class, 'start'])->name('test.import.start');
Route::get('/test-import/view/{id}', [TestImportController::class, 'view'])->name('test.import.view');


Route::get('/', function () {
    return view('welcome');
});
