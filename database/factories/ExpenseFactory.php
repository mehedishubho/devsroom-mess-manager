<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'date' => now()->toDateString(),
            'vendor' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'expense_type' => 'bazar',
        ];
    }
}
