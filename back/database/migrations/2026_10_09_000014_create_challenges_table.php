<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desafios do evento, com ou sem empresa (desafio institucional).
 * FK composta (company_id, event_id): a empresa é sempre do mesmo evento.
 * O banco garante status válidos; a semântica do fluxo fica no ChallengeService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('title');
            $table->text('problem');
            $table->text('objective')->nullable();
            $table->text('requirements')->nullable();
            $table->text('restrictions')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->timestamps();

            $table->foreign(['company_id', 'event_id'])->references(['id', 'event_id'])->on('companies')->restrictOnDelete();
            // Alvo da FK composta (challenge_id, event_id) de teams.
            $table->unique(['id', 'event_id']);
            $table->index(['event_id', 'status']);
            $table->index('company_id');
        });

        DB::statement("alter table challenges add constraint challenges_status_check check (status in ('DRAFT', 'RECEIVED', 'UNDER_REVIEW', 'APPROVED', 'DISTRIBUTED', 'IN_DEVELOPMENT', 'FINISHED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
