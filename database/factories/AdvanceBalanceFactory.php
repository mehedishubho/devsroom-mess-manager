<?php

namespace Database\Factories;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvanceBalanceFactory extends Factory
{
    protected $model = AdvanceBalance::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'member_id' => Member::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 5000),
            'last_updated_at' => now(),
        ];
    }
}
