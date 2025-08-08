<?php

use App\Http\Controllers\AssignmentDownloadController;
use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/d/{assignment}', [AssignmentDownloadController::class, 'download'])
    ->name('assignments.download');

Route::get('/offer/{batch}/{channel}', [OfferController::class, 'show'])->name('offer.show');
Route::get('/offer/{batch}/{channel}/zip', [OfferController::class, 'zip'])->name('offer.zip');
