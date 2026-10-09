<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ocorrências (algo que aconteceu), setores envolvidos adicionais e histórico contextual.
 * Todas as relações respeitam o evento por FKs compostas com event_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create sequence if not exists occurrences_reference_seq');

        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('reference', 20)->default(new Expression("next_reference('OCO', 'occurrences_reference_seq')"))->unique();
            $table->string('title');
            $table->text('description');
            $table->string('category', 20);
            $table->unsignedBigInteger('origin_sector_id');
            $table->unsignedBigInteger('responsible_sector_id');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('priority', 10)->default('MEDIUM');
            $table->string('status', 16)->default('OPEN');
            $table->unsignedBigInteger('event_day_id')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->text('notes')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign(['origin_sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->foreign(['responsible_sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->foreign(['event_day_id', 'event_id'])->references(['id', 'event_id'])->on('event_days')->restrictOnDelete();
            $table->foreign(['team_id', 'event_id'])->references(['id', 'event_id'])->on('teams')->restrictOnDelete();
            $table->unique(['id', 'event_id']);
            $table->index(['event_id', 'status']);
            $table->index(['responsible_sector_id', 'status']);
            $table->index('origin_sector_id');
            $table->index('assigned_user_id');
            $table->index('event_day_id');
            $table->index('team_id');
        });

        DB::statement('alter sequence occurrences_reference_seq owned by occurrences.reference');
        DB::statement("alter table occurrences add constraint occurrences_status_check check (status in ('OPEN', 'IN_PROGRESS', 'RESOLVED'))");
        DB::statement("alter table occurrences add constraint occurrences_priority_check check (priority in ('LOW', 'MEDIUM', 'HIGH', 'URGENT'))");
        DB::statement("alter table occurrences add constraint occurrences_category_check check (category in ('TECHNOLOGY', 'INFRASTRUCTURE', 'PRODUCTION', 'PARTICIPANT', 'TEAM', 'COMPANY', 'ORGANIZATION', 'OTHER'))");
        DB::statement("alter table occurrences add constraint occurrences_reference_check check (reference ~ '^OCO-[0-9]{4,}$')");
        DB::statement("alter table occurrences add constraint occurrences_resolved_at_check check ((status = 'RESOLVED') = (resolved_at is not null))");

        Schema::create('occurrence_sectors', function (Blueprint $table) {
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('sector_id');
            $table->unsignedBigInteger('event_id');

            $table->primary(['occurrence_id', 'sector_id']);
            $table->foreign(['occurrence_id', 'event_id'])->references(['id', 'event_id'])->on('occurrences')->restrictOnDelete();
            $table->foreign(['sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->index('sector_id');
        });

        Schema::create('occurrence_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['occurrence_id', 'id']);
        });

        DB::statement("alter table occurrence_interactions add constraint occurrence_interactions_type_check check (type in ('CREATED', 'UPDATED', 'COMMENT', 'STATUS_CHANGED', 'FORWARDED', 'ASSIGNEE_CHANGED', 'PRIORITY_CHANGED', 'DUE_CHANGED', 'SECTOR_ADDED', 'SECTOR_REMOVED', 'COMPLETED', 'RESOLVED', 'REOPENED', 'TASK_GENERATED'))");
        DB::unprepared('create trigger occurrence_interactions_append_only before update or delete on occurrence_interactions for each row execute function forbid_update_delete()');
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_interactions');
        Schema::dropIfExists('occurrence_sectors');
        Schema::dropIfExists('occurrences');
    }
};
