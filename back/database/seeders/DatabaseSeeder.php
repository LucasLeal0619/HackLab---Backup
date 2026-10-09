<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Credenciais previsíveis e dados fictícios só em local/testing; os próprios seeders recusam outros ambientes.
        $this->call(DevelopmentAdminSeeder::class);
        $this->call(DevelopmentDataSeeder::class);
    }
}
