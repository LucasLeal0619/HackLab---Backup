<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A equipe recebe o desafio: teams.challenge_id é a fonte única da relação (não existe challenges.team_id).
 * 1:1 nesta versão (unique). Para várias equipes por desafio no futuro, basta remover o unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('challenge_id')->nullable()->after('code');

            // Nulos não conflitam: várias equipes sem desafio são permitidas.
            $table->unique('challenge_id');
            $table->foreign(['challenge_id', 'event_id'])->references(['id', 'event_id'])->on('challenges')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['challenge_id', 'event_id']);
            $table->dropUnique(['challenge_id']);
            $table->dropColumn('challenge_id');
        });
    }
};
