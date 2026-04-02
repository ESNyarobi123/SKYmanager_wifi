<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Thin backward-compatibility alias for User with role 'customer'.
 *
 * After the Spatie auth refactor, customers are stored in the `users` table.
 * This class exists solely so that existing code referencing App\Models\Customer
 * continues to work without modification (factories, tests, controllers, etc.).
 * All behaviour lives in App\Models\User.
 */
class Customer extends User
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $table = 'users';
}
