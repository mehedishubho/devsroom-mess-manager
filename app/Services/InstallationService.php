<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class InstallationService
{
    public const INSTALLED_KEY = 'installed';

    public function isInstalled(): bool
    {
        if (! Schema::hasTable('app_settings')) {
            return false;
        }

        $setting = AppSetting::query()
            ->where('key', self::INSTALLED_KEY)
            ->first();

        return (bool) ($setting?->value['installed'] ?? false);
    }

    public function hasAdministrator(): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return User::query()->whereHas('roles', function ($query): void {
            $query->where('slug', 'super-admin');
        })->exists();
    }

    public function shouldRunSetup(): bool
    {
        return ! $this->isInstalled() && ! $this->hasAdministrator();
    }

    public function markInstalled(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::INSTALLED_KEY],
            ['value' => [
                'installed' => true,
                'installed_at' => now()->toISOString(),
            ]]
        );
    }
}
