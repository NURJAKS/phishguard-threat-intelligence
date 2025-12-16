<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckController;

Route::get('/', [CheckController::class, 'dashboard'])->name('home'); // Redirect home to dashboard or keep as landing
Route::get('/dashboard', [CheckController::class, 'dashboard'])->name('dashboard');
Route::get('/check/phone', [CheckController::class, 'index'])->name('check.phone'); // Reusing index as phone check for now
Route::post('/check/phone', [CheckController::class, 'check'])->name('check.perform');

Route::get('/check/listing', [CheckController::class, 'listing'])->name('check.listing');
Route::post('/check/listing', [CheckController::class, 'checkListing'])->name('check.listing.perform');
Route::get('/check/image', [CheckController::class, 'image'])->name('check.image');
Route::post('/check/image', [CheckController::class, 'checkImage'])->name('check.image.perform');
Route::get('/check/link', [CheckController::class, 'link'])->name('check.link');

Route::get('/connections', [CheckController::class, 'connections'])->name('connections');
Route::get('/profile', [CheckController::class, 'profile'])->name('profile');
Route::get('/analytics', [CheckController::class, 'analytics'])->name('analytics');
Route::get('/history', [CheckController::class, 'history'])->name('check.history');
Route::get('/settings', [CheckController::class, 'settings'])->name('settings');
