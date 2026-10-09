<?php

namespace App\Domain\Auth;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Login e logout da SPA (Sanctum, sessão por cookie).
 */
class AuthService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws ValidationException credenciais inválidas
     * @throws AuthorizationException conta inativa
     */
    public function login(string $email, string $password, Request $request): User
    {
        if (! $request->hasSession()) {
            // Sem sessão = requisição fora da SPA configurada (SANCTUM_STATEFUL_DOMAINS).
            throw new BadRequestHttpException('Login disponível só para o frontend do HackLab (sessão por cookie).');
        }

        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->audit->record(
                AuditAction::LOGIN_FAILED,
                'auth',
                'Tentativa de login recusada.',
                after: ['email' => $email],
            );

            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        if (! $user->isActive()) {
            $this->audit->record(
                AuditAction::LOGIN_BLOCKED_INACTIVE,
                'auth',
                'Login recusado: conta inativa.',
                entity: $user,
                actor: $user,
            );

            throw new AuthorizationException('Conta inativa.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->record(AuditAction::LOGIN, 'auth', 'Login realizado.', entity: $user, actor: $user);

        return $user;
    }

    public function logout(Request $request): void
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user !== null) {
            $this->audit->record(AuditAction::LOGOUT, 'auth', 'Logout realizado.', entity: $user, actor: $user);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
