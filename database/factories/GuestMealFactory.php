<?php

namespace Database\Factories;

use App\Models\GuestMeal;
use App\Models\Member;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestMealFactory extends Factory
{
    protected $model = GuestMeal::class;

    public function definition(): array
    {
        $quantity = 1.0;
        $mealValue = 50.00;

        return [
            'mess_id' => Mess::factory(),
            'member_id' => Member::factory(),
            'guest_name' => $this->faker->name(),
            'date' => now()->toDateString(),
            'meal_type' => 'lunch',
            'quantity' => $quantity,
            'meal_value' => $mealValue,
            'charge_amount' => $quantity * $mealValue,
        ];
    }
}
