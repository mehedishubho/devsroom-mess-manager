<?php

namespace App\Services\GoogleSheets;

use App\Models\AdvanceBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The DB→Sheets tab layout: which models are mirrored, the tab each one lives
 * in, its header row, and how a model is flattened into a row.
 *
 * Flattening rules (kept consistent with the app's existing Excel exports):
 *  - person foreign keys render as the related NAME, never the raw id;
 *  - dates render as `Y-m-d`; datetimes as `Y-m-d H:i:s`;
 *  - money renders as a numeric float so the sheet can SUM it;
 *  - booleans render as 1/0.
 *
 * Column A is always the model's primary key: the sync engine uses it to find
 * the row to update or delete. Never reorder column A.
 *
 * Every row closure is null-safe — a missing relation renders as '' rather than
 * throwing, so one bad row can't break a whole flush.
 */
class SheetSchema
{
    /**
     * model class => ['tab' => string, 'headers' => list<string>, 'row' => callable]
     *
     * @var array<string, array{tab: string, headers: list<string>, row: callable}>|null
     */
    private static ?array $tables = null;

    /** @return array<string, array{tab: string, headers: list<string>, row: callable}> */
    public static function tables(): array
    {
        return self::$tables ??= [
            MealEntry::class => [
                'tab' => 'MealEntries',
                'headers' => ['ID', 'Date', 'Member', 'Breakfast', 'Lunch', 'Dinner', 'Guest Breakfast', 'Guest Lunch', 'Guest Dinner', 'Entered By', 'Updated At'],
                'row' => fn (MealEntry $m) => [
                    (int) $m->id,
                    self::date($m->date),
                    $m->member?->name ?? '',
                    self::flag($m->breakfast),
                    self::flag($m->lunch),
                    self::flag($m->dinner),
                    self::money($m->guest_breakfast),
                    self::money($m->guest_lunch),
                    self::money($m->guest_dinner),
                    $m->enteredBy?->name ?? '',
                    self::dateTime($m->updated_at),
                ],
            ],

            GuestMeal::class => [
                'tab' => 'GuestMeals',
                // GuestMeal has no enteredBy relation, so the operator name is omitted
                // rather than emitting a raw user id.
                'headers' => ['ID', 'Date', 'Host Member', 'Guest Name', 'Meal Type', 'Quantity', 'Meal Value', 'Charge Amount'],
                'row' => fn (GuestMeal $m) => [
                    (int) $m->id,
                    self::date($m->date),
                    $m->member?->name ?? '',
                    (string) ($m->guest_name ?? ''),
                    (string) ($m->meal_type ?? ''),
                    self::money($m->quantity),
                    self::money($m->meal_value),
                    self::money($m->charge_amount),
                ],
            ],

            MealOffRequest::class => [
                'tab' => 'MealOffRequests',
                'headers' => ['ID', 'Member', 'From Date', 'To Date', 'Status', 'Reason', 'Rejection Reason', 'Acted By', 'Acted At'],
                'row' => fn (MealOffRequest $m) => [
                    (int) $m->id,
                    $m->member?->name ?? '',
                    self::date($m->from_date),
                    self::date($m->to_date),
                    (string) ($m->status ?? ''),
                    (string) ($m->reason ?? ''),
                    (string) ($m->rejection_reason ?? ''),
                    $m->actedBy?->name ?? '',
                    self::dateTime($m->acted_at),
                ],
            ],

            ExpenseCategory::class => [
                'tab' => 'ExpenseCategories',
                'headers' => ['ID', 'Name', 'Kind', 'Default', 'Sort Order'],
                'row' => fn (ExpenseCategory $m) => [
                    (int) $m->id,
                    (string) ($m->name ?? ''),
                    (string) ($m->kind ?? ''),
                    self::flag($m->is_default),
                    (int) ($m->sort_order ?? 0),
                ],
            ],

            Expense::class => [
                'tab' => 'Expenses',
                'headers' => ['ID', 'Date', 'Category', 'Kind', 'Purchased By', 'Vendor', 'Description', 'Amount', 'Entered By'],
                'row' => fn (Expense $m) => [
                    (int) $m->id,
                    self::date($m->date),
                    $m->category?->name ?? '',
                    (string) ($m->category?->kind ?? ''),
                    $m->purchasedByMember?->name ?? '',
                    (string) ($m->vendor ?? ''),
                    (string) ($m->description ?? ''),
                    self::money($m->amount),
                    $m->enteredBy?->name ?? '',
                ],
            ],

            Payment::class => [
                'tab' => 'Payments',
                'headers' => ['ID', 'Date', 'Member', 'Type', 'Method', 'Amount', 'Reference', 'Notes', 'Entered By'],
                'row' => fn (Payment $m) => [
                    (int) $m->id,
                    self::date($m->date),
                    $m->member?->name ?? '',
                    (string) ($m->type ?? ''),
                    (string) ($m->method ?? ''),
                    self::money($m->amount),
                    (string) ($m->reference ?? ''),
                    (string) ($m->notes ?? ''),
                    $m->enteredBy?->name ?? '',
                ],
            ],

            AdvanceBalance::class => [
                'tab' => 'AdvanceBalances',
                'headers' => ['ID', 'Member', 'Balance', 'Due Balance', 'Net Balance', 'Last Updated'],
                'row' => fn (AdvanceBalance $m) => [
                    (int) $m->id,
                    $m->member?->name ?? '',
                    self::money($m->balance),
                    self::money($m->due_balance),
                    self::money($m->netBalance()),
                    self::dateTime($m->last_updated_at),
                ],
            ],

            Member::class => [
                'tab' => 'Members',
                'headers' => ['ID', 'Name', 'Slug', 'Mobile', 'Email', 'Profession', 'Room/Seat', 'Joining Date', 'Leaving Date', 'Status', 'Emergency Contact'],
                'row' => fn (Member $m) => [
                    (int) $m->id,
                    (string) ($m->name ?? ''),
                    (string) ($m->slug ?? ''),
                    (string) ($m->mobile ?? ''),
                    (string) ($m->email ?? ''),
                    (string) ($m->profession ?? ''),
                    (string) ($m->room_or_seat ?? ''),
                    self::date($m->joining_date),
                    self::date($m->leaving_date),
                    (string) ($m->status ?? ''),
                    (string) ($m->emergency_contact ?? ''),
                ],
            ],

            MonthlyClosing::class => [
                'tab' => 'MonthlyClosings',
                'headers' => ['ID', 'Year', 'Month', 'Total Bazar', 'Total Fixed', 'Total Meals', 'Meal Rate', 'Member Count', 'Closed At', 'Closed By', 'Status'],
                'row' => fn (MonthlyClosing $m) => [
                    (int) $m->id,
                    (int) ($m->year ?? 0),
                    (int) ($m->month ?? 0),
                    self::money($m->total_bazar),
                    self::money($m->total_fixed_expense),
                    self::money($m->total_meals),
                    self::money($m->meal_rate),
                    (int) ($m->member_count ?? 0),
                    self::dateTime($m->closed_at),
                    $m->closedBy?->name ?? '',
                    (string) ($m->status ?? ''),
                ],
            ],

            MonthlyMemberSummary::class => [
                'tab' => 'MonthlyMemberSummaries',
                // `advance_applied` is misnamed in the schema — it holds the
                // bill-payment-type payments applied this month, so the header
                // reads "Bill Payments" (CR-03).
                'headers' => ['ID', 'Closing', 'Member', 'Total Meals', 'Meal Rate', 'Meal Cost', 'Fixed Share', 'Guest Charge', 'Gross Bill', 'Bill Payments', 'Net Bill', 'Payments Received', 'Balance Due', 'Brought Forward', 'Closing Balance'],
                'row' => fn (MonthlyMemberSummary $m) => [
                    (int) $m->id,
                    self::closingLabel($m->monthlyClosing),
                    $m->member?->name ?? '',
                    self::money($m->total_meals),
                    self::money($m->meal_rate),
                    self::money($m->meal_cost),
                    self::money($m->fixed_cost_share),
                    self::money($m->guest_meal_charge),
                    self::money($m->gross_bill),
                    self::money($m->advance_applied),
                    self::money($m->net_bill),
                    self::money($m->payments_received),
                    self::money($m->balance_due),
                    self::money($m->brought_forward),
                    self::money($m->closing_balance),
                ],
            ],
        ];
    }

    /** @return list<string> Every mirrored model class. */
    public static function models(): array
    {
        return array_keys(self::tables());
    }

    public static function tabFor(string $model): ?string
    {
        return self::tables()[$model]['tab'] ?? null;
    }

    /** Stable form/array key for a model (e.g. MealEntry => "meal_entries"). */
    public static function keyFor(string $model): ?string
    {
        $tab = self::tabFor($model);

        return $tab === null ? null : Str::snake($tab);
    }

    /** Resolve a form key back to its model class. */
    public static function modelForKey(string $key): ?string
    {
        foreach (array_keys(self::tables()) as $model) {
            if (self::keyFor($model) === $key) {
                return $model;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function headersFor(string $model): array
    {
        return self::tables()[$model]['headers'] ?? [];
    }

    /**
     * The tab descriptors the gateway needs for a set of models.
     *
     * @param  array<int, string>  $models
     * @return array<int, array{title: string, headers: array<int, string>}>
     */
    public static function tabDefinitions(array $models): array
    {
        $tables = self::tables();
        $definitions = [];

        foreach (array_unique($models) as $model) {
            if (isset($tables[$model])) {
                $definitions[] = [
                    'title' => $tables[$model]['tab'],
                    'headers' => $tables[$model]['headers'],
                ];
            }
        }

        return $definitions;
    }

    /** Flatten a model instance into a sheet row. */
    public static function rowFor(Model $model): array
    {
        $table = self::tables()[$model::class] ?? null;

        if ($table === null) {
            return [];
        }

        return ($table['row'])($model);
    }

    /** Relations a caller should eager-load before flattening a batch. */
    public static function relationsFor(string $model): array
    {
        return match ($model) {
            MealEntry::class => ['member:id,name', 'enteredBy:id,name'],
            GuestMeal::class => ['member:id,name'],
            MealOffRequest::class => ['member:id,name', 'actedBy:id,name'],
            Expense::class => ['category:id,name,kind', 'purchasedByMember:id,name', 'enteredBy:id,name'],
            Payment::class => ['member:id,name', 'enteredBy:id,name'],
            AdvanceBalance::class => ['member:id,name'],
            MonthlyClosing::class => ['closedBy:id,name'],
            MonthlyMemberSummary::class => ['member:id,name', 'monthlyClosing:id,year,month'],
            default => [],
        };
    }

    private static function money(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function flag(mixed $value): int
    {
        return $value ? 1 : 0;
    }

    private static function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? substr($value, 0, 10) : '';
    }

    private static function dateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) ? $value : '';
    }

    private static function closingLabel(?MonthlyClosing $closing): string
    {
        if ($closing === null) {
            return '';
        }

        return $closing->year.'-'.str_pad((string) $closing->month, 2, '0', STR_PAD_LEFT);
    }
}
