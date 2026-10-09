<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evento (Hackathon) e seus dias. Quantidade de dias, datas e nome vêm do banco, nunca do código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('PLANNED')->index();
            $table->timestamps();
        });

        DB::statement("alter table events add constraint events_status_check check (status in ('PLANNED', 'ACTIVE', 'FINISHED', 'CANCELLED'))");
        DB::statement('alter table events add constraint events_dates_check check (end_date >= start_date)');

        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('date');
            $table->string('label', 120);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'day_number']);
            $table->unique(['event_id', 'date']);
        });

        DB::statement('alter table event_days add constraint event_days_day_number_check check (day_number >= 1)');
        DB::statement('alter table event_days add constraint event_days_times_check check (start_time is null or end_time is null or end_time > start_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
        Schema::dropIfExists('events');
    }
};
