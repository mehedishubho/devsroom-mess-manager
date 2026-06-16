<?php

namespace Database\Factories;

use App\Models\Mess;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'user_id' => User::factory(),
            'type' => 'test.notification',
            'data' => ['message' => $this->faker->sentence()],
        ];
    }
}
