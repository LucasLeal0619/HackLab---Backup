<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conta de acesso ao HackLab. Pertence a exatamente uma pessoa (people 1 ─ 0..1 users).
 *
 * Sem dados de participante ou jurado aqui. O vínculo com setor (sector_id) entra
 * na migration da Fase 2, junto com a tabela `sectors`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();
            $table->foreignId('role_id')->index()->constrained()->restrictOnDelete();
            // Sempre gravado em minúsculas (App\Models\User), então o unique vale sem diferença de caixa.
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement("alter table users add constraint users_status_check check (status in ('ACTIVE', 'INACTIVE'))");
        DB::statement('alter table users add constraint users_email_lowercase_check check (email = lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
