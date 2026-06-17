<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mess\AdvanceBalanceController;
use App\Http\Controllers\Mess\BillPreviewController;
use App\Http\Controllers\Mess\AuditController;
use App\Http\Controllers\Mess\ExpenseCategoryController;
use App\Http\Controllers\Mess\ExpenseController;
use App\Http\Controllers\Mess\GuestMealController;
use App\Http\Controllers\Mess\ManagerMealOffController;
use App\Http\Controllers\Mess\MealGridController;
use App\Http\Controllers\Mess\MealOffApprovalController;
use App\Http\Controllers\Mess\MemberController;
use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\Mess\MemberSearchController;
use App\Http\Controllers\Mess\MessConfigController;
use App\Http\Controllers\Mess\PaymentController;
use App\Http\Controllers\My\MyPaymentController;
use App\Http\Controllers\My\MyBillPreviewController;
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

    Route::get('mess/meal-off', [MealOffApprovalController::class, 'index'])->name('mess.meal-off.index');
    Route::patch('mess/meal-off/{mealOffRequest}/approve', [MealOffApprovalController::class, 'approve'])->name('mess.meal-off.approve');
    Route::patch('mess/meal-off/{mealOffRequest}/reject', [MealOffApprovalController::class, 'reject'])->name('mess.meal-off.reject');

    Route::get('mess/expenses', [ExpenseController::class, 'index'])->name('mess.expenses.index');
    Route::get('mess/expenses/bazar/create', [ExpenseController::class, 'createBazar'])->name('mess.expenses.bazar.create');
    Route::post('mess/expenses/bazar', [ExpenseController::class, 'storeBazar'])->name('mess.expenses.bazar.store');
    Route::get('mess/expenses/fixed/create', [ExpenseController::class, 'createFixed'])->name('mess.expenses.fixed.create');
    Route::post('mess/expenses/fixed', [ExpenseController::class, 'storeFixed'])->name('mess.expenses.fixed.store');

    Route::get('mess/categories', [ExpenseCategoryController::class, 'index'])->name('mess.categories.index');
    Route::post('mess/categories', [ExpenseCategoryController::class, 'store'])->name('mess.categories.store');
    Route::delete('mess/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('mess.categories.destroy');

    Route::resource('mess/payments', PaymentController::class)
        ->names([
            'index' => 'mess.payments.index',
            'create' => 'mess.payments.create',
            'store' => 'mess.payments.store',
            'show' => 'mess.payments.show',
            'edit' => 'mess.payments.edit',
            'update' => 'mess.payments.update',
            'destroy' => 'mess.payments.destroy',
        ]);

    Route::get('mess/advance-balances', [AdvanceBalanceController::class, 'index'])->name('mess.advance-balances.index');
    Route::get('mess/advance-balances/{member}/adjust', [AdvanceBalanceController::class, 'adjust'])->name('mess.advance-balances.adjust');
    Route::post('mess/advance-balances/{member}/adjust', [AdvanceBalanceController::class, 'storeAdjust'])->name('mess.advance-balances.storeAdjust');

    Route::get('mess/bill-preview', [BillPreviewController::class, 'index'])->name('mess.bill-preview.index');
});

// Member (user role) home
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/my', [MyController::class, 'index'])->name('my');
    Route::patch('my/profile', [MyController::class, 'updateProfile'])->name('my.profile.update');
    Route::post('my/meal-off', [MyController::class, 'storeMealOff'])->name('my.meal-off.store');
    Route::get('my/payments', [MyPaymentController::class, 'index'])->name('my.payments');
    Route::get('my/bill-preview', [MyBillPreviewController::class, 'index'])->name('my.bill-preview');
});
