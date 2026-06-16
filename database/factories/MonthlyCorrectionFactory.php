<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyCorrectionFactory extends Factory
{
    protected $model = MonthlyCorrection::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'monthly_closing_id' => MonthlyClosing::factory(),
            'member_id' => Member::factory(),
            'applied_to_year' => now()->year,
            'applied_to_month' => now()->month,
            'amount' => $this->faker->randomFloat(2, -500, 500),
            'reason' => $this->faker->sentence(),
            'entered_by' => User::factory(),
        ];
    }
}
