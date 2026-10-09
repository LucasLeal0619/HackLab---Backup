<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\DatabaseTestCase;

class AuditLogTest extends DatabaseTestCase
{
    public function test_sensitive_keys_are_never_stored(): void
    {
        $log = app(AuditLogger::class)->record('TEST', 'tests', 'Teste de sanitização.', after: [
            'email' => 'a@hacklab.test',
            'password' => 'segredo1',
            'password_confirmation' => 'segredo1',
            'remember_token' => 'abc',
            'nested' => ['api_token' => 'xyz', 'Authorization' => 'Bearer x', 'session_cookie' => 'c', 'ok' => 1],
        ]);

        $this->assertSame(['email' => 'a@hacklab.test', 'nested' => ['ok' => 1]], $log->fresh()->after_data);
    }

    public function test_audit_log_cannot_be_updated_or_deleted_via_model(): void
    {
        $log = app(AuditLogger::class)->record('TEST', 'tests', 'Imutável.');

        $this->expectException(LogicException::class);
        $log->update(['description' => 'alterado']);
    }

    public function test_database_trigger_blocks_update(): void
    {
        app(AuditLogger::class)->record('TEST', 'tests', 'Imutável.');

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->update(['description' => 'alterado']);
    }

    public function test_database_trigger_blocks_delete(): void
    {
        app(AuditLogger::class)->record('TEST', 'tests', 'Imutável.');

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->delete();
    }

    public function test_records_actor_ip_and_user_agent(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withHeader('User-Agent', 'HackLabTest/1.0')
            ->postJson('/api/v1/people', ['full_name' => 'Auditada'])
            ->assertCreated();

        $log = AuditLog::query()->latest('id')->first();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($admin->person_id, $log->actor_person_id);
        $this->assertSame('HackLabTest/1.0', $log->user_agent);
        $this->assertNotNull($log->ip_address);
    }
}
