<?php

namespace Database\Factories;

use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyClosingFactory extends Factory
{
    protected $model = MonthlyClosing::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'year' => now()->year,
            'month' => now()->month,
            'total_bazar' => 50000.00,
            'total_fixed_expense' => 12000.00,
            'total_meals' => 600.00,
            'meal_rate' => 80.0000,
            'member_count' => 10,
            'closed_at' => now(),
            'closed_by' => User::factory(),
            'status' => 'closed',
        ];
    }
}
