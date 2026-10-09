<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): AuthenticatedUserResource
    {
        $user = $this->auth->login($request->validated('email'), $request->validated('password'), $request);

        return AuthenticatedUserResource::make($user->load('person', 'role'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request);

        return response()->noContent();
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return AuthenticatedUserResource::make($request->user()->load('person', 'role'));
    }
}
