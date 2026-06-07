<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
        'name'                    => $this->faker->name(),
        'email'                   => $this->faker->unique()->safeEmail(),
        'phone'                   => '05' . $this->faker->unique()->numerify('#########'),
        'phone_verified_at'       => null,
        'password'                => Hash::make('password'),
        'status'                  => 'pending',
        'agent_type'              => null,
        'admin_note'              => null,
        'company_name'            => null,
        'subscription_plan'       => 'free',
        'subscription_started_at' => null,
        'subscription_ends_at'    => null,
        'offer_limit'             => 0,
        'is_banned'               => false,
        'ban_reason'              => null,
        'remember_token'          => Str::random(10),
    ];
    }

    // ── State'ler ─────────────────────────────────────────────

    public function active(): static
    {
        return $this->state(fn () => [
            'status'            => 'active',
            'phone_verified_at' => now(),
        ]);
    }

    public function buyer(): static
    {
        return $this->active()->afterCreating(fn ($user) => $user->assignRole('buyer'));
    }

    public function agent(string $agentType = 'emlakci'): static
    {
        return $this->active()->state(fn () => [
            'agent_type'   => $agentType,
            'company_name' => $this->faker->company(),
            'offer_limit'  => 10,
        ])->afterCreating(fn ($user) => $user->assignRole('agent'));
    }

    public function banned(): static
    {
        return $this->state(fn () => [
            'is_banned'  => true,
            'ban_reason' => 'Kural ihlali',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'phone_verified_at' => null,
        ]);
    }
}