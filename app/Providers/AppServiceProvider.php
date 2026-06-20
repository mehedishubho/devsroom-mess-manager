<?php

namespace App\Providers;

use App\Listeners\NotifyOnBackupFailure;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

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
                if ($user->hasRole('admin') || $user->hasRole('manager')) {
                    return '/home';
                }
                if ($user->hasRole('user')) {
                    return '/my';
                }

                return '/';
            },
        ]);

        $this->registerBillPreviewInvalidation();
        $this->registerBackupFailureListeners();
    }

    /**
     * D-05: wire spatie backup failure events to the project's notification
     * surface. class_exists-guarded so prod (composer install --no-dev with
     * spatie present) wires the listeners, and any env without spatie does
     * not error. Mirrors the telescope:prune pattern in routes/console.php.
     */
    private function registerBackupFailureListeners(): void
    {
        if (! class_exists(BackupHasFailed::class)) {
            return;
        }

        Event::listen(
            BackupHasFailed::class,
            NotifyOnBackupFailure::class
        );
        Event::listen(
            UnhealthyBackupWasFound::class,
            NotifyOnBackupFailure::class
        );
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

        // Phase 4 DASH-05: also forget the dashboard counts cache for the
        // affected month. The key is scoped by mess_id (T-04-03-01) so
        // cross-mess bleed is impossible. This extends the EXISTING listener
        // body — NO second Event::listen() call (preserves < 2s refresh,
        // success #12). Same hook fires for both saved + deleted events.
        try {
            $carbon = Carbon::parse($dateStr);
        } catch (\Throwable) {
            return;
        }

        $messId = Mess::activeId();
        if ($messId === null) {
            return;
        }

        Cache::forget(
            "dash:counts:{$messId}:{$carbon->year}-".str_pad((string) $carbon->month, 2, '0', STR_PAD_LEFT)
        );
    }
}
