<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 *
 * Backward-compatible factory: previously targeted App\Models\Customer.
 * Now creates User records with the 'customer' role.
 * All existing tests that call Customer::factory() use this factory via the
 * Customer model alias defined in app/Models/Customer.php.
 */
class CustomerFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'phone' => '255'.fake()->numerify('#########'),
            'company_name' => fake()->optional(0.4)->company(),
            'referral_code' => strtoupper(fake()->lexify('????????')),
            'is_suspended' => false,
            'onboarding_completed' => false,
            'password' => 'password',
            'phone_verified_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_suspended' => true,
        ]);
    }
}
