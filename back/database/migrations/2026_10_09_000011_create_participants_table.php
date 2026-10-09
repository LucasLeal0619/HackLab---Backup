<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Participante = aluno que desenvolve solução no Hackathon. Identidade (nome, e-mail...) fica em people.
 * FK composta (class_id, event_id): a turma é sempre do mesmo evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('status', 16)->default('AVAILABLE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'person_id']);
            // Alvo da FK composta (participant_id, event_id) de team_members.
            $table->unique(['id', 'event_id']);
            $table->foreign(['class_id', 'event_id'])->references(['id', 'event_id'])->on('classes')->restrictOnDelete();
            $table->index(['event_id', 'status']);
            $table->index('class_id');
            $table->index('person_id');
        });

        DB::statement("alter table participants add constraint participants_status_check check (status in ('AVAILABLE', 'UNAVAILABLE', 'WITHDRAWN'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
