<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Credencial previsível só em local/testing; o próprio seeder recusa outros ambientes.
        $this->call(DevelopmentAdminSeeder::class);
    }
}
