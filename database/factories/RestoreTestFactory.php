<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RestoreTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestoreTest>
 */
class RestoreTestFactory extends Factory
{
    protected $model = RestoreTest::class;

    public function definition(): array
    {
        return [
            'status' => 'passed',
            'per_table_counts' => [
                ['table' => 'members', 'live' => 1, 'test' => 1, 'pass' => true],
            ],
            'message' => null,
            'ran_at' => now(),
        ];
    }
}
