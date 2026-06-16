<?php

namespace Database\Factories;

use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealEntryFactory extends Factory
{
    protected $model = MealEntry::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'member_id' => Member::factory(),
            'date' => now()->toDateString(),
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ];
    }
}
