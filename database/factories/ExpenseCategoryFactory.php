<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'mess_id' => Mess::activeId() ?? Mess::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
