<?php

namespace App\Support;

use App\Models\Mess;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class MealType
{
    public const BREAKFAST = 'breakfast';

    public const LUNCH = 'lunch';

    public const DINNER = 'dinner';

    public const ALL = [self::BREAKFAST, self::LUNCH, self::DINNER];

    /** Fallback weights used when no per-mess setting exists yet. */
    public const DEFAULT_BREAKFAST = 0.5;

    public const DEFAULT_LUNCH = 1.0;

    public const DEFAULT_DINNER = 1.0;

    /** Setting keys that hold each meal type's configured weight. */
    public const SETTING_KEYS = [
        self::BREAKFAST => 'meal_breakfast',
        self::LUNCH => 'meal_lunch',
        self::DINNER => 'meal_dinner',
    ];

    /**
     * The configured weight of a meal type for the active mess (full = 1.0,
     * half = 0.5, etc.), falling back to the defaults above when no setting
     * is stored (e.g. before onboarding, or in a console context with no
     * resolved mess). Results are cached per mess+key for an hour; call
     * forgetFor() after an admin edits the values.
     */
    public static function value(string $type): float
    {
        return match ($type) {
            self::BREAKFAST => self::configured(self::BREAKFAST, self::DEFAULT_BREAKFAST),
            self::LUNCH => self::configured(self::LUNCH, self::DEFAULT_LUNCH),
            self::DINNER => self::configured(self::DINNER, self::DEFAULT_DINNER),
            default => 1.0,
        };
    }

    /**
     * Read one meal weight from the per-mess settings table (cached).
     * Stored shape: { "amount": <float> } — see OnboardingController.
     */
    private static function configured(string $type, float $default): float
    {
        $messId = Mess::activeId();
        if ($messId === null) {
            return $default;
        }

        $key = self::SETTING_KEYS[$type] ?? null;
        if ($key === null) {
            return $default;
        }

        return (float) Cache::remember(
            self::cacheKey($messId, $key),
            now()->addHour(),
            function () use ($messId, $key, $default) {
                $setting = Setting::query()
                    ->where('mess_id', $messId)
                    ->where('key', $key)
                    ->first();

                $amount = $setting?->value['amount'] ?? null;

                return is_numeric($amount) ? (float) $amount : $default;
            }
        );
    }

    public static function cacheKey(int $messId, string $key): string
    {
        return "meal-weight:{$messId}:{$key}";
    }

    /**
     * Drop the cached weight(s) for a mess so the next read picks up edits.
     * Pass null to forget all three meal-type weights at once.
     */
    public static function forgetFor(int $messId, ?string $type = null): void
    {
        $types = $type !== null ? [$type] : self::ALL;

        foreach ($types as $t) {
            $key = self::SETTING_KEYS[$t] ?? null;
            if ($key !== null) {
                Cache::forget(self::cacheKey($messId, $key));
            }
        }
    }
}
