<?php

namespace Tests\Feature\Database;

use App\Support\Database\DatabaseEnvironmentGuard;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DatabaseEnvironmentGuardTest extends TestCase
{
    private const SUPABASE_POOLER = 'aws-0-sa-east-1.pooler.supabase.com';

    public function test_tests_cannot_open_the_dev_database(): void
    {
        config(['database.connections.pgsql.database' => 'hacklab_dev']);
        DB::purge('pgsql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Testes só podem usar um banco terminado em "_test"');

        DB::connection('pgsql');
    }

    public function test_tests_cannot_open_supabase_even_with_a_test_database_name(): void
    {
        config(['database.connections.pgsql.host' => self::SUPABASE_POOLER]);
        DB::purge('pgsql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não pode conectar no Supabase');

        DB::connection('pgsql');
    }

    public function test_tests_may_use_the_local_test_database(): void
    {
        (new DatabaseEnvironmentGuard('testing'))->check($this->connection(['database' => 'hacklab_test']));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('supabaseHosts')]
    public function test_local_environment_cannot_use_supabase(string $host): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não pode conectar no Supabase');

        (new DatabaseEnvironmentGuard('local'))->check($this->connection(['host' => $host, 'sslmode' => 'require']));
    }

    public static function supabaseHosts(): array
    {
        return [
            'session pooler' => [self::SUPABASE_POOLER],
            'conexão direta' => ['db.abcdefghijklmnop.supabase.co'],
            'maiúsculas' => ['DB.ABCDEFGHIJKLMNOP.SUPABASE.CO'],
        ];
    }

    public function test_production_may_use_supabase_with_ssl(): void
    {
        (new DatabaseEnvironmentGuard('production'))->check($this->connection([
            'host' => self::SUPABASE_POOLER,
            'database' => 'postgres',
            'sslmode' => 'require',
        ]));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('insecureSslModes')]
    public function test_production_refuses_insecure_sslmode(?string $sslmode): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Produção recusa');

        (new DatabaseEnvironmentGuard('production'))->check($this->connection([
            'host' => self::SUPABASE_POOLER,
            'database' => 'postgres',
            'sslmode' => $sslmode,
        ]));
    }

    public static function insecureSslModes(): array
    {
        return [['disable'], ['allow'], ['prefer'], [null]];
    }

    private function connection(array $config): PostgresConnection
    {
        $config = array_merge([
            'driver' => 'pgsql',
            'name' => 'pgsql',
            'host' => 'postgres',
            'database' => 'hacklab_dev',
            'sslmode' => 'disable',
        ], $config);

        return new PostgresConnection(fn () => null, $config['database'], '', $config);
    }
}
