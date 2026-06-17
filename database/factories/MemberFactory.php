<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'name' => $this->faker->name(),
            'mobile' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'profession' => $this->faker->jobTitle(),
            'room_or_seat' => 'R-'.$this->faker->numberBetween(101, 399),
            'joining_date' => now()->subMonths($this->faker->numberBetween(1, 12))->toDateString(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function former(): static
    {
        return $this->state([
            'status' => 'former',
            'leaving_date' => now()->subDays(30)->toDateString(),
        ]);
    }
}
