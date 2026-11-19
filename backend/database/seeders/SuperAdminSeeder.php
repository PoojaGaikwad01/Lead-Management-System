<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Auth\Role;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('lms.super_admin');

        if (blank($email) || blank($password)) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set in .env; super admin not created.');

            return;
        }

        User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'active' => true,
        ]);
    }
}
