<?php

namespace Database\Factories;

use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessFactory extends Factory
{
    protected $model = Mess::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Mess',
            'address' => $this->faker->address(),
            'monthly_rent' => $this->faker->randomElement([0, 5000, 8000, 12000, 15000, 20000]),
            'manager_contact' => $this->faker->phoneNumber(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
