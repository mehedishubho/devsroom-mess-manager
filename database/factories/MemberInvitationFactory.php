<?php

namespace Database\Factories;

use App\Models\MemberInvitation;
use App\Models\Mess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MemberInvitationFactory extends Factory
{
    protected $model = MemberInvitation::class;

    public function definition(): array
    {
        return [
            'mess_id' => Mess::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Str::random(48),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDay(),
        ];
    }
}
