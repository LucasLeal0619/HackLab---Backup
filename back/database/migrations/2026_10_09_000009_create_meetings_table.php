<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reuniões do evento: gerais (sector_id nulo) ou setoriais.
 * FK composta (sector_id, event_id) garante que o setor é do mesmo evento da reunião.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sector_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('scheduled_at');
            $table->string('location')->nullable();
            $table->string('status', 16)->default('SCHEDULED');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->index(['event_id', 'scheduled_at']);
            $table->index(['sector_id', 'scheduled_at']);
        });

        DB::statement("alter table meetings add constraint meetings_status_check check (status in ('SCHEDULED', 'DONE', 'CANCELLED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
