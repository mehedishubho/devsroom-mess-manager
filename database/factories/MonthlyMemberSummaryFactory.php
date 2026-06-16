<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyMemberSummaryFactory extends Factory
{
    protected $model = MonthlyMemberSummary::class;

    public function definition(): array
    {
        $meals = 60.00;
        $rate = 80.0000;
        $mealCost = $meals * $rate;
        $fixedShare = 1200.00;
        $gross = $mealCost + $fixedShare;

        return [
            'mess_id' => Mess::factory(),
            'monthly_closing_id' => MonthlyClosing::factory(),
            'member_id' => Member::factory(),
            'total_meals' => $meals,
            'meal_rate' => $rate,
            'meal_cost' => $mealCost,
            'fixed_cost_share' => $fixedShare,
            'guest_meal_charge' => 0.00,
            'gross_bill' => $gross,
            'advance_applied' => 0.00,
            'net_bill' => $gross,
            'payments_received' => 0.00,
            'balance_due' => $gross,
        ];
    }
}
