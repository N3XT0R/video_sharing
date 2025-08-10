<?php

use App\Http\Controllers\AssignmentDownloadController;
use App\Http\Controllers\DropboxController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ZipController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/game', function () {
    return view('game');
})->name('game');

Route::get('/changelog', function () {
    return Str::markdown(file_get_contents(base_path('CHANGELOG.md')));
})->name('changelog');


Route::get('/offer/{batch}/{channel}', [OfferController::class, 'show'])->name('offer.show');
Route::post('/offer/{batch}/{channel}/zip', [OfferController::class, 'zipSelected'])->name('offer.zip.selected');
Route::get('/offer/{batch}/{channel}/unused', [OfferController::class, 'showUnused'])->name('offer.unused.show');
Route::post('/offer/{batch}/{channel}/unused', [OfferController::class, 'storeUnused'])->name('offer.unused.store');

Route::get('/d/{assignment}', [AssignmentDownloadController::class, 'download'])->name('assignments.download');


Route::get('/dropbox/connect', [DropboxController::class, 'connect'])->name('dropbox.connect');
Route::get('/dropbox/callback', [DropboxController::class, 'callback'])->name('dropbox.callback');

Route::post('/zips/{batch}/{channel}', [ZipController::class, 'start']);
Route::get('/zips/{id}/progress', [ZipController::class, 'progress']);
Route::get('/zips/{id}/download', [ZipController::class, 'download']);