<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\AuthService;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\Security\Authorization\Authorize;
use Throwable;

#[RouteGroup(prefix: '/auth', version: 'v1', tags: ['Auth'])]
final class AuthController
{
   public function __construct(private AuthService $authService)
   {
   }

   #[Route(methods: ['POST'], path: '/login', summary: 'Login with username/password and receive JWT')]
   public function login(Request $request): Response
   {
      $body = $request->getParsedBody();
      $payload = is_array($body) ? $body : [];

      $username = trim((string) ($payload['username'] ?? ''));
      $password = (string) ($payload['password'] ?? '');

      if ($username === '' || $password === '') {
         return $this->json(422, [
            'error' => 'validation_failed',
            'message' => 'Both username and password are required.',
         ]);
      }

      try {
         $tokenPayload = $this->authService->attemptLogin($username, $password);
      } catch (Throwable $exception) {
         return $this->json(500, [
            'error' => 'server_misconfigured',
            'message' => $exception->getMessage(),
         ]);
      }

      if ($tokenPayload === null) {
         return $this->json(401, [
            'error' => 'invalid_credentials',
            'message' => 'Invalid username or password.',
         ]);
      }

      return $this->json(200, $tokenPayload);
   }

   #[Authorize]
   #[Route(methods: ['GET'], path: '/me', summary: 'Return authenticated user claims')]
   public function me(RequestContext $ctx): Response
   {
      return $this->json(200, [
         'auth' => $ctx->getAuth(),
      ]);
   }

   /**
    * @param array<string, mixed> $payload
    */
   private function json(int $status, array $payload): Response
   {
      return new Response(
         $status,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
      );
   }
}
