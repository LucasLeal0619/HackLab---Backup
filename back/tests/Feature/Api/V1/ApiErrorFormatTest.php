<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/_test')->middleware('api')->group(function () {
            Route::post('/validation', fn (Request $request) => $request->validate(['name' => 'required']));
            Route::get('/auth', fn () => 'ok')->middleware('auth:sanctum');
            Route::get('/forbidden', fn () => abort(403));
            Route::get('/crash', fn () => throw new RuntimeException('segredo interno'));
        });
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->get('/api/v1/nao-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.', 'errors' => []]);
    }

    public function test_wrong_method_returns_json_405(): void
    {
        $this->post('/api/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Método não permitido.');
    }

    public function test_validation_error_keeps_field_errors(): void
    {
        $this->postJson('/api/v1/_test/validation', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_unauthenticated_returns_json_401(): void
    {
        $this->get('/api/v1/_test/auth')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Não autenticado.', 'errors' => []]);
    }

    public function test_forbidden_returns_json_403(): void
    {
        $this->getJson('/api/v1/_test/forbidden')
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso negado.');
    }

    public function test_server_error_hides_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/crash')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Erro interno do servidor.', 'errors' => []]);

        $this->assertStringNotContainsString('segredo interno', $response->getContent());
    }

    public function test_server_error_shows_debug_info_when_debug_is_on(): void
    {
        config(['app.debug' => true]);

        $this->getJson('/api/v1/_test/crash')
            ->assertStatus(500)
            ->assertJsonPath('debug.exception', RuntimeException::class);
    }
}
