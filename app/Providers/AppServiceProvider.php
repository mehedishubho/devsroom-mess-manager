<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Mess;
use App\Models\Payment;
use App\Services\BillPreviewInvalidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('en');

        // D-02: role-based post-login redirect.
        config([
            'tyro-login.redirects.after_login' => function () {
                $user = Auth::user() ?? auth()->user();
                if (! $user) {
                    return '/';
                }
                if ($user->hasRole('super-admin')) {
                    if (! Mess::query()->exists()) {
                        return route('onboarding.create');
                    }

                    return '/dashboard';
                }
                if ($user->hasRole('admin')) {
                    return '/home';
                }
                if ($user->hasRole('user')) {
                    return '/my';
                }

                return '/';
            },
        ]);

        $this->registerBillPreviewInvalidation();
    }

    private function registerBillPreviewInvalidation(): void
    {
        $invalidator = $this->app->make(BillPreviewInvalidator::class);

        $models = [MealEntry::class, GuestMeal::class, MealOffRequest::class, Expense::class, Payment::class];
        foreach ($models as $modelClass) {
            Event::listen("eloquent.saved: {$modelClass}", function ($event) use ($invalidator) {
                $this->invalidateForModel($invalidator, $event->model);
            });
            Event::listen("eloquent.deleted: {$modelClass}", function ($event) use ($invalidator) {
                $this->invalidateForModel($invalidator, $event->model);
            });
        }
    }

    private function invalidateForModel(BillPreviewInvalidator $invalidator, $model): void
    {
        $date = $model->date ?? $model->created_at ?? now();
        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
        $invalidator->forDate($dateStr);
    }
}