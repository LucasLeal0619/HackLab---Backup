<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jurados (papel de domínio de uma Person no evento; não é conta nem representante)
 * e atribuições explícitas jurado ↔ equipe, com histórico (REVOKED, reativação no mesmo registro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            // Só contexto: a empresa nunca define as equipes avaliadas.
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
            $table->unique(['id', 'event_id']);
            $table->foreign(['company_id', 'event_id'])->references(['id', 'event_id'])->on('companies')->restrictOnDelete();
            $table->index(['event_id', 'status']);
            $table->index('person_id');
        });

        DB::statement("alter table jurors add constraint jurors_status_check check (status in ('ACTIVE', 'INACTIVE'))");

        Schema::create('juror_team_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('juror_id');
            $table->unsignedBigInteger('team_id');
            $table->string('status', 16)->default('ACTIVE');
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            // Reativar reutiliza o registro; também é o alvo da FK de evaluations.
            $table->unique(['juror_id', 'team_id']);
            $table->foreign(['juror_id', 'event_id'])->references(['id', 'event_id'])->on('jurors')->restrictOnDelete();
            $table->foreign(['team_id', 'event_id'])->references(['id', 'event_id'])->on('teams')->restrictOnDelete();
            $table->index(['team_id', 'status']);
            $table->index(['event_id', 'status']);
        });

        DB::statement("alter table juror_team_assignments add constraint juror_team_assignments_status_check check (status in ('ACTIVE', 'REVOKED'))");
        DB::statement("alter table juror_team_assignments add constraint juror_team_assignments_revoked_check check ((status = 'REVOKED') = (revoked_at is not null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('juror_team_assignments');
        Schema::dropIfExists('jurors');
    }
};
