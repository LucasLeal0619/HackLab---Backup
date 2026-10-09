<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseUp = $this->databaseIsUp();

        return response()->json([
            'data' => [
                'status' => $databaseUp ? 'ok' : 'degraded',
                'service' => 'hacklab-api',
                'api_version' => 'v1',
                'database' => $databaseUp ? 'ok' : 'unavailable',
                'timestamp' => now()->toIso8601String(),
            ],
        ], $databaseUp ? 200 : 503);
    }

    private function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $e) {
            // Detalhe só no log; a resposta pública não expõe host nem erro do banco.
            Log::warning('Health check: banco indisponível', ['exception' => $e::class]);

            return false;
        }
    }
}
