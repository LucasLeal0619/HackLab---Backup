<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DbCheck extends Command
{
    protected $signature = 'hacklab:db-check {--connection= : Conexão a verificar (padrão: DB_CONNECTION)}';

    protected $description = 'Valida a conexão com o PostgreSQL do HackLab: servidor, banco, schema, SSL e migrations';

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');

        try {
            $connection = DB::connection($name);

            if ($connection->getDriverName() !== 'pgsql') {
                $this->error("A conexão \"{$name}\" usa o driver \"{$connection->getDriverName()}\". O HackLab usa pgsql.");

                return self::FAILURE;
            }

            $expectedSchema = $connection->getConfig('search_path');

            $info = $connection->selectOne(<<<'SQL'
                select
                    current_setting('server_version') as version,
                    current_database() as database,
                    current_user as "user",
                    current_schema() as schema,
                    current_setting('search_path') as search_path,
                    current_setting('server_encoding') as encoding,
                    coalesce((select ssl from pg_stat_ssl where pid = pg_backend_pid()), false) as ssl
                SQL);

            $hasMigrationsTable = $info->schema !== null
                && $connection->getSchemaBuilder()->hasTable('migrations');

            $migrations = $hasMigrationsTable ? $connection->table('migrations')->count() : null;
        } catch (Throwable $e) {
            $this->error('Falha ao conectar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Item', 'Valor'], [
            ['Ambiente (APP_ENV)', app()->environment()],
            ['Conexão', $name],
            ['Host', $connection->getConfig('host').':'.$connection->getConfig('port')],
            ['Servidor', 'PostgreSQL '.$info->version],
            ['Banco', $info->database],
            ['Usuário', $info->user],
            ['Encoding', $info->encoding],
            ['search_path', $info->search_path],
            ['Schema ativo', $info->schema ?? '(nenhum: o schema não existe)'],
            ['SSL', ($info->ssl ? 'sim' : 'não').' (sslmode '.$connection->getConfig('sslmode').')'],
            ['Migrations aplicadas', $migrations === null ? 'nenhuma (rode php artisan migrate)' : (string) $migrations],
        ]);

        if ($info->schema !== $expectedSchema) {
            $this->error("O schema \"{$expectedSchema}\" não existe neste banco. Crie-o antes do migrate (veja hacklab_docs/docs/banco-de-dados.md).");

            return self::FAILURE;
        }

        $this->info('Conexão OK.');

        return self::SUCCESS;
    }
}
