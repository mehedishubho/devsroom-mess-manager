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
use Illuminate\Database\Eloquent\Model;
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
            // Laravel passes the model instance directly to eloquent.saved/deleted
            // listeners — there is no event object with a ->model property (CR-01).
            Event::listen("eloquent.saved: {$modelClass}", function (Model $model) use ($invalidator) {
                $this->invalidateForModel($invalidator, $model);
            });
            Event::listen("eloquent.deleted: {$modelClass}", function (Model $model) use ($invalidator) {
                $this->invalidateForModel($invalidator, $model);
            });
        }
    }

    private function invalidateForModel(BillPreviewInvalidator $invalidator, Model $model): void
    {
        // Resolve the date that reflects the AFFECTED month (CR-01 / WR-02):
        //  - MealOffRequest uses from_date (the requested meal-off date), not date.
        //  - MealEntry/GuestMeal/Expense/Payment use date.
        // Only fall back to created_at when the model genuinely lacks a business
        // date column — never to now(), which would invalidate the wrong month.
        $date = match (true) {
            isset($model->date) => $model->date,
            isset($model->from_date) => $model->from_date,
            isset($model->created_at) => $model->created_at,
            default => null,
        };

        if ($date === null) {
            return;
        }

        $dateStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
        $invalidator->forDate($dateStr);
    }
}
