<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoa real conhecida pelo HackLab. Pode existir sem conta de acesso (users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            // E-mail ajuda a identificar, mas não é identidade absoluta: não é único.
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable();
            $table->string('document', 32)->nullable();
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->timestamps();
        });

        DB::statement("alter table people add constraint people_status_check check (status in ('ACTIVE', 'INACTIVE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
