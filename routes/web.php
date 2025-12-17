<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckController;

// Главная страница - перенаправление на проверку ссылок
Route::get('/', [CheckController::class, 'link'])->name('home');

// Проверка ссылок
Route::get('/check/link', [CheckController::class, 'link'])->name('check.link');
Route::post('/check/link', [CheckController::class, 'checkLink'])->name('check.link.perform');
