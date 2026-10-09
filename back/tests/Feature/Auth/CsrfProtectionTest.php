<?php

namespace Tests\Feature\Auth;

use App\Domain\Users\Enums\RoleCode;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\DatabaseTestCase;

/**
 * O Laravel desliga a verificação de CSRF em testes. Aqui ela é religada para provar
 * que requisições da SPA (stateful no Sanctum) exigem o token.
 */
class CsrfProtectionTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->userWithRole(RoleCode::Voter, ['email' => 'votante@hacklab.test']);
    }

    public function test_spa_login_without_csrf_token_is_rejected(): void
    {
        $this->fromSpa()
            ->postJson('/api/v1/auth/login', ['email' => 'votante@hacklab.test', 'password' => 'password1'])
            ->assertStatus(419)
            ->assertJsonPath('message', 'Sessão expirada ou token CSRF inválido.');

        $this->assertGuest('web');
    }

    public function test_spa_login_with_csrf_token_is_accepted(): void
    {
        $this->withSession(['_token' => 'token-csrf-de-teste'])
            ->fromSpa()
            ->withHeader('X-CSRF-TOKEN', 'token-csrf-de-teste')
            ->postJson('/api/v1/auth/login', ['email' => 'votante@hacklab.test', 'password' => 'password1'])
            ->assertOk();
    }

    public function test_csrf_cookie_endpoint_issues_the_xsrf_cookie(): void
    {
        $this->fromSpa()->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }
}
