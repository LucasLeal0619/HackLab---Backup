<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pendências (algo que precisa ser feito), setores envolvidos adicionais e histórico contextual.
 * source_occurrence_id não é unique: uma ocorrência pode gerar várias pendências.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create sequence if not exists tasks_reference_seq');

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('reference', 20)->default(new Expression("next_reference('PEN', 'tasks_reference_seq')"))->unique();
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('origin_sector_id');
            $table->unsignedBigInteger('responsible_sector_id');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('priority', 10)->default('MEDIUM');
            $table->string('status', 16)->default('PENDING');
            $table->timestampTz('due_at')->nullable();
            $table->unsignedBigInteger('source_occurrence_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign(['origin_sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->foreign(['responsible_sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->foreign(['source_occurrence_id', 'event_id'])->references(['id', 'event_id'])->on('occurrences')->restrictOnDelete();
            $table->unique(['id', 'event_id']);
            $table->index(['event_id', 'status']);
            $table->index(['responsible_sector_id', 'status']);
            $table->index('origin_sector_id');
            $table->index('assigned_user_id');
            $table->index('source_occurrence_id');
            $table->index('due_at');
        });

        DB::statement('alter sequence tasks_reference_seq owned by tasks.reference');
        DB::statement("alter table tasks add constraint tasks_status_check check (status in ('PENDING', 'IN_PROGRESS', 'COMPLETED'))");
        DB::statement("alter table tasks add constraint tasks_priority_check check (priority in ('LOW', 'MEDIUM', 'HIGH', 'URGENT'))");
        DB::statement("alter table tasks add constraint tasks_reference_check check (reference ~ '^PEN-[0-9]{4,}$')");
        DB::statement("alter table tasks add constraint tasks_resolved_at_check check ((status = 'COMPLETED') = (resolved_at is not null))");

        Schema::create('task_sectors', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('sector_id');
            $table->unsignedBigInteger('event_id');

            $table->primary(['task_id', 'sector_id']);
            $table->foreign(['task_id', 'event_id'])->references(['id', 'event_id'])->on('tasks')->restrictOnDelete();
            $table->foreign(['sector_id', 'event_id'])->references(['id', 'event_id'])->on('sectors')->restrictOnDelete();
            $table->index('sector_id');
        });

        Schema::create('task_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['task_id', 'id']);
        });

        DB::statement("alter table task_interactions add constraint task_interactions_type_check check (type in ('CREATED', 'UPDATED', 'COMMENT', 'STATUS_CHANGED', 'FORWARDED', 'ASSIGNEE_CHANGED', 'PRIORITY_CHANGED', 'DUE_CHANGED', 'SECTOR_ADDED', 'SECTOR_REMOVED', 'COMPLETED', 'RESOLVED', 'REOPENED', 'TASK_GENERATED'))");
        DB::unprepared('create trigger task_interactions_append_only before update or delete on task_interactions for each row execute function forbid_update_delete()');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_interactions');
        Schema::dropIfExists('task_sectors');
        Schema::dropIfExists('tasks');
    }
};
