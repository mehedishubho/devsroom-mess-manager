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
            'mess_id' => Mess::activeId() ?? Mess::factory(),
            'member_id' => Member::factory(),
            'balance' => 0,
            'due_balance' => 0,
            'last_updated_at' => now(),
        ];
    }

    public function withAdvance(float $amount): self
    {
        return $this->state(['balance' => $amount, 'due_balance' => 0]);
    }

    public function withDue(float $amount): self
    {
        return $this->state(['balance' => 0, 'due_balance' => $amount]);
    }
}
