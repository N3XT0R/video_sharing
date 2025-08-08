<?php

use App\Http\Controllers\AssignmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/d/{assignment}', [AssignmentDownloadController::class, 'download'])
    ->name('assignments.download');