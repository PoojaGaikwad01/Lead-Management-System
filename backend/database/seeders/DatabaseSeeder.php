<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LeadTypeSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
