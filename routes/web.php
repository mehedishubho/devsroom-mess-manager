<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('welcome');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
});

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/my', [MyController::class, 'index'])->name('my');
});
