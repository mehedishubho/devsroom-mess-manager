<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'member_id' => Member::factory(),
            'date' => now()->toDateString(),
            'amount' => $this->faker->randomFloat(2, 500, 10000),
            'method' => 'cash',
            'type' => 'bill_payment',
        ];
    }
}
