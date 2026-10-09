<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Impede que um ambiente use o banco de outro.
 *
 * - fora de production: nunca um host do Supabase (o banco de produção);
 * - testing: só bancos terminados em `_test` (os testes apagam o banco);
 * - production: PostgreSQL só com sslmode que não cai para texto puro.
 */
class DatabaseEnvironmentGuard
{
    public const SECURE_SSLMODES = ['require', 'verify-ca', 'verify-full'];

    private const SUPABASE_HOST_SUFFIXES = ['.supabase.co', '.supabase.com'];

    public function __construct(private readonly string $environment) {}

    public function check(Connection $connection): void
    {
        $name = $connection->getName();
        $host = strtolower((string) $connection->getConfig('host'));
        $database = (string) $connection->getDatabaseName();

        if ($this->environment !== 'production' && $this->isSupabaseHost($host)) {
            throw new RuntimeException(sprintf(
                'O ambiente "%s" não pode conectar no Supabase (conexão "%s", host "%s"). Use o PostgreSQL do Docker.',
                $this->environment,
                $name,
                $host,
            ));
        }

        if ($this->environment === 'testing' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Testes só podem usar um banco terminado em "_test". Conexão "%s" aponta para "%s".',
                $name,
                $database,
            ));
        }

        if ($this->environment === 'production'
            && $connection->getDriverName() === 'pgsql'
            && ! in_array($connection->getConfig('sslmode'), self::SECURE_SSLMODES, true)) {
            throw new RuntimeException(sprintf(
                'Produção recusa a conexão "%s" com sslmode "%s". Use %s.',
                $name,
                $connection->getConfig('sslmode') ?? '(vazio)',
                implode(', ', self::SECURE_SSLMODES),
            ));
        }
    }

    private function isSupabaseHost(string $host): bool
    {
        foreach (self::SUPABASE_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
