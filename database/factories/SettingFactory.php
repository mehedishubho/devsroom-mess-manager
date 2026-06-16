<?php

namespace Database\Factories;

use App\Models\Mess;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'key' => $this->faker->unique()->slug(2),
            'value' => ['default' => true],
            'type' => 'string',
            'group' => 'general',
        ];
    }
}
