<?php

use App\Http\Controllers\AssignmentDownloadController;
use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/offer/{batch}/{channel}', [OfferController::class, 'show'])->name('offer.show');
Route::post('/offer/{batch}/{channel}/zip', [OfferController::class, 'zipSelected'])->name('offer.zip.selected');
Route::get('/offer/{batch}/{channel}/unused', [OfferController::class, 'showUnused'])->name('offer.unused.show');
Route::post('/offer/{batch}/{channel}/unused', [OfferController::class, 'storeUnused'])->name('offer.unused.store');

Route::get('/d/{assignment}', [AssignmentDownloadController::class, 'download'])->name('assignments.download');