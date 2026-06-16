<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mess\AuditController;
use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\Mess\MessConfigController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SetPasswordController;
use App\Http\Middleware\EnsureMessExists;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('welcome');

// Onboarding (super-admin only)
Route::middleware(['auth', 'role:super-admin', EnsureMessExists::class])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

// Public set-password (from invite link)
Route::get('/set-password', [SetPasswordController::class, 'show'])->name('password.set.show');
Route::post('/set-password', [SetPasswordController::class, 'update'])->name('password.set.update');

// Manager (admin role) — home + mess config + audit + invite
Route::middleware(['auth', 'role:admin', EnsureMessExists::class])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::get('/mess/settings', [MessConfigController::class, 'edit'])->name('mess.settings.edit');
    Route::patch('/mess/settings', [MessConfigController::class, 'update'])->name('mess.settings.update');

    Route::get('/mess/audit', [AuditController::class, 'index'])->name('mess.audit');

    Route::get('/mess/members/invite', [MemberInviteController::class, 'create'])->name('mess.members.invite.create');
    Route::post('/mess/members/invite', [MemberInviteController::class, 'store'])->name('mess.members.invite.store');
});

// Member (user role) home
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/my', [MyController::class, 'index'])->name('my');
});
