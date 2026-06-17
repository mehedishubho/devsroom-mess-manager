<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mess\AuditController;
use App\Http\Controllers\Mess\GuestMealController;
use App\Http\Controllers\Mess\ManagerMealOffController;
use App\Http\Controllers\Mess\MealGridController;
use App\Http\Controllers\Mess\MemberController;
use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\Mess\MemberSearchController;
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

    Route::resource('mess/members', MemberController::class)
        ->except(['destroy'])
        ->names([
            'index' => 'mess.members.index',
            'create' => 'mess.members.create',
            'store' => 'mess.members.store',
            'show' => 'mess.members.show',
            'edit' => 'mess.members.edit',
            'update' => 'mess.members.update',
        ]);

    Route::patch('mess/members/{member}/deactivate', [MemberController::class, 'destroy'])
        ->name('mess.members.deactivate');

    Route::get('mess/members-search', MemberSearchController::class)
        ->name('mess.members.search');

    Route::post('mess/members/{member}/meal-off', [ManagerMealOffController::class, 'store'])
        ->name('mess.members.meal-off.store');

    Route::get('mess/meals', [MealGridController::class, 'index'])->name('mess.meals.index');
    Route::post('mess/meals', [MealGridController::class, 'save'])->name('mess.meals.save');

    Route::get('mess/guest-meals', [GuestMealController::class, 'index'])->name('mess.guest-meals.index');
    Route::get('mess/guest-meals/create', [GuestMealController::class, 'create'])->name('mess.guest-meals.create');
    Route::post('mess/guest-meals', [GuestMealController::class, 'store'])->name('mess.guest-meals.store');
    Route::get('mess/guest-meals/{guestMeal}/edit', [GuestMealController::class, 'edit'])->name('mess.guest-meals.edit');
    Route::patch('mess/guest-meals/{guestMeal}', [GuestMealController::class, 'update'])->name('mess.guest-meals.update');
});

// Member (user role) home
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/my', [MyController::class, 'index'])->name('my');
    Route::patch('my/profile', [MyController::class, 'updateProfile'])->name('my.profile.update');
    Route::post('my/meal-off', [MyController::class, 'storeMealOff'])->name('my.meal-off.store');
});
