<?php

namespace Database\Factories;

use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MealOffStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealOffRequestFactory extends Factory
{
    protected $model = MealOffRequest::class;

    public function definition(): array
    {
        $from = now();
        $to = $from->copy()->addDays($this->faker->numberBetween(1, 5));

        return [
            'mess_id' => Mess::factory(),
            'member_id' => Member::factory(),
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => MealOffStatus::PENDING]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => MealOffStatus::APPROVED,
            'acted_at' => now(),
            'acted_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => MealOffStatus::REJECTED,
            'acted_at' => now(),
            'acted_by' => User::factory(),
        ]);
    }
}
