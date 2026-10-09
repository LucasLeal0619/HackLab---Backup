<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Formato único de erro da API: { "message": string, "errors": object }.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        // O Laravel embrulha algumas exceções em HttpException antes de chegar aqui; usa a original.
        $previous = $e->getPrevious();
        if ($e instanceof HttpExceptionInterface && (
            $previous instanceof AuthorizationException
            || $previous instanceof TokenMismatchException
            || $previous instanceof ModelNotFoundException
        )) {
            $e = $previous;
        }

        [$status, $message, $errors, $headers] = match (true) {
            $e instanceof ValidationException => [$e->status, $e->getMessage(), $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'Não autenticado.', [], []],
            $e instanceof AuthorizationException => [$e->status() ?? 403, self::authorizationMessage($e), [], []],
            $e instanceof ModelNotFoundException => [404, 'Recurso não encontrado.', [], []],
            $e instanceof TokenMismatchException => [419, 'Sessão expirada ou token CSRF inválido.', [], []],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                self::httpMessage($e),
                [],
                $e->getHeaders(),
            ],
            default => [500, 'Erro interno do servidor.', [], []],
        };

        $body = [
            'message' => $message,
            'errors' => (object) $errors,
        ];

        if (config('app.debug') && $status >= 500) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * Mantém mensagens específicas (ex.: "Conta inativa."); a padrão do framework vira "Acesso negado.".
     */
    private static function authorizationMessage(AuthorizationException $e): string
    {
        $message = $e->getMessage();

        return $message === '' || $message === 'This action is unauthorized.' ? 'Acesso negado.' : $message;
    }

    private static function httpMessage(HttpExceptionInterface $e): string
    {
        return match ($e->getStatusCode()) {
            403 => 'Acesso negado.',
            404 => 'Recurso não encontrado.',
            405 => 'Método não permitido.',
            429 => 'Muitas requisições. Tente novamente em instantes.',
            503 => 'Serviço indisponível.',
            // Mensagem própria da aplicação (ex.: abort(400, '...')); senão, o texto padrão do status.
            default => $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$e->getStatusCode()] ?? 'Erro.'),
        };
    }
}
