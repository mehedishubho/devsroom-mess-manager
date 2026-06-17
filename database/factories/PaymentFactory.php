<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::activeId() ?? Mess::factory(),
            'member_id' => Member::factory(),
            'date' => now()->toDateString(),
            'amount' => $this->faker->randomFloat(2, 500, 10000),
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
        ];
    }

    public function advanceDeposit(): self
    {
        return $this->state(['type' => PaymentType::ADVANCE_DEPOSIT]);
    }

    public function cash(): self
    {
        return $this->state(['method' => PaymentMethod::CASH]);
    }

    public function bkash(): self
    {
        return $this->state(['method' => PaymentMethod::BKASH, 'reference' => 'BK'.now()->timestamp]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(['member_id' => $member->id, 'mess_id' => $member->mess_id]);
    }
}
