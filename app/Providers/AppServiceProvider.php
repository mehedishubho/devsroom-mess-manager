<?php

namespace App\Providers;

use App\Listeners\NotifyOnBackupFailure;
use App\Models\Expense;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\MemberDisabledDay;
use App\Models\Mess;
use App\Models\MessClosedDay;
use App\Models\Payment;
use App\Services\BillPreviewInvalidator;
use App\Support\CloudBackupCredentials;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
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

        // Apply DB-stored cloud credentials BEFORE the google-drive driver is
        // registered and before any disk is resolved, so every request/command
        // sees the UI-configured Google Drive + R2 values (with .env fallback).
        $this->applyCloudCredentials();

        $this->registerGoogleDriveDriver();
        $this->registerBillPreviewInvalidation();
        $this->registerBackupFailureListeners();
    }

    /**
     * Push DB-stored Google Drive + R2 credentials into the runtime filesystem
     * config. Wrapped so a fresh clone (missing backup_configs columns / table)
     * never fatals the boot path — degrades silently to env-derived values.
     */
    private function applyCloudCredentials(): void
    {
        try {
            CloudBackupCredentials::applyToRuntimeConfig();
        } catch (\Throwable) {
            // Bootstrap-safe: env values remain in effect.
        }
    }

    /**
     * Register the custom 'google-drive' filesystem driver (Task 1 of
     * quick-260717-2q3). Laravel ships no built-in Google Drive adapter, so
     * Storage::extend() is the canonical registration path.
     *
     * class_exists-guarded so the app boots cleanly even when
     * masbug/flysystem-google-drive-ext is not installed — the disks become
     * unreachable at runtime, and StorageProvider/BackupDestinations skip
     * them gracefully (T-2q3-03 DoS mitigation).
     */
    private function registerGoogleDriveDriver(): void
    {
        if (! class_exists(GoogleDriveAdapter::class)) {
            return;
        }

        Storage::extend('google-drive', function (\Illuminate\Contracts\Filesystem\Filesystem $app, array $config) {
            $client = new Client;
            $client->setClientId($config['clientId'] ?? '');
            $client->setClientSecret($config['clientSecret'] ?? '');
            $client->refreshTokenWithRefreshToken($config['refreshToken'] ?? '');

            $service = new Drive($client);
            $adapter = new GoogleDriveAdapter($service, $config['folderId'] ?? null);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
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

        $models = [MealEntry::class, GuestMeal::class, MealOffRequest::class, Expense::class, Payment::class, MessClosedDay::class, MemberDisabledDay::class];
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
