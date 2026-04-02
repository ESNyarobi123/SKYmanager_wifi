<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Default state: admin-style user (email login, no phone).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'company_name' => null,
            'referral_code' => null,
            'referred_by' => null,
            'is_suspended' => false,
            'onboarding_completed' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->roles()->exists()) {
                $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $user->assignRole($role);
            }
        });
    }

    /**
     * Create a customer-role user (phone login, customer-specific fields).
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->optional(0.7)->safeEmail(),
            'phone' => '255'.fake()->numerify('#########'),
            'company_name' => fake()->optional(0.4)->company(),
            'referral_code' => strtoupper(fake()->lexify('????????')),
            'is_suspended' => false,
            'onboarding_completed' => false,
            'phone_verified_at' => now(),
        ])->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }

    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }

    public function reseller(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'reseller', 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }

    /**
     * Customer suspended state.
     */
    public function suspended(): static
    {
        return $this->customer()->state(fn (array $attributes) => [
            'is_suspended' => true,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
