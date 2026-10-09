<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Grava ações sensíveis em audit_logs.
 *
 * Nunca grava senha, token, cookie ou segredo: as chaves sensíveis são removidas
 * de before/after em qualquer nível.
 */
class AuditLogger
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'token', 'secret', 'cookie', 'authorization', 'api_key', 'remember',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        string $module,
        string $description,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::guard('web')->user();

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'actor_person_id' => $actor?->person_id,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey() === null ? null : (string) $entity->getKey(),
            'description' => $description,
            'before_data' => $before === null ? null : $this->sanitize($before),
            'after_data' => $after === null ? null : $this->sanitize($after),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
