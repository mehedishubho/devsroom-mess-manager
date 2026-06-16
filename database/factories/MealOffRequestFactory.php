<?php

namespace Database\Factories;

use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
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
}
