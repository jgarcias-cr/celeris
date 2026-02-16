# Celeris API + React SPA: Passwordless Auth (Axios Only)

This example shows a basic passwordless flow using:
- Celeris API (JWT strategy enabled)
- One-time verification code (email-style challenge)
- React SPA using Axios only

Flow:
1. User enters email.
2. Frontend calls `POST /api/auth/passwordless/start`.
3. Backend generates code + challenge ID, stores challenge with short TTL, and sends code through your delivery channel.
4. User enters code.
5. Frontend calls `POST /api/auth/passwordless/verify`.
6. Backend verifies challenge and returns JWT.
7. Frontend uses `Authorization: Bearer <token>` for protected routes.

## 1. Backend environment

In `packages/api-stub/.env`, set at least:

```dotenv
SECURITY_AUTH_JWT_ENABLED=true
JWT_SECRET=change-this-to-a-long-random-secret
JWT_TTL_SECONDS=3600

PASSWORDLESS_CODE_TTL_SECONDS=300
PASSWORDLESS_DEV_MODE=true
```

`PASSWORDLESS_DEV_MODE=true` is only for local development. In production, keep it `false`.

## 2. Backend example (Celeris)

### 2.1 Add config values

In `packages/api-stub/config/security.php`, add:

```php
'passwordless' => [
    'code_ttl_seconds' => $envInt('PASSWORDLESS_CODE_TTL_SECONDS', 300),
    'dev_mode' => $envBool('PASSWORDLESS_DEV_MODE', false),
],
```

### 2.2 Add passwordless service

Create `app/Services/PasswordlessAuthService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Celeris\Framework\Cache\CacheEngine;
use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Config\ConfigRepository;
use RuntimeException;

final class PasswordlessAuthService
{
    public function __construct(
        private ConfigRepository $config,
        private CacheEngine $cache,
    ) {
    }

    /** @return array<string, mixed> */
    public function start(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email format.');
        }

        $code = (string) random_int(100000, 999999);
        $challengeId = bin2hex(random_bytes(16));
        $ttl = max(60, (int) $this->config->get('security.passwordless.code_ttl_seconds', 300));

        $payload = [
            'email' => $normalizedEmail,
            'code_hash' => hash('sha256', $code),
            'created_at' => time(),
        ];

        $this->cache->put(
            CacheIntent::write('auth.passwordless', $challengeId, $ttl),
            $payload,
        );

        // Replace with your real provider (email/SMS/push). Do not return code in production.
        $response = [
            'challenge_id' => $challengeId,
            'expires_in' => $ttl,
        ];

        if ((bool) $this->config->get('security.passwordless.dev_mode', false)) {
            $response['dev_code'] = $code;
        }

        return $response;
    }

    /** @return array<string, mixed>|null */
    public function verify(string $challengeId, string $email, string $code): ?array
    {
        $challengeId = trim($challengeId);
        $normalizedEmail = strtolower(trim($email));
        $normalizedCode = trim($code);

        if ($challengeId === '' || $normalizedEmail === '' || $normalizedCode === '') {
            return null;
        }

        $entry = $this->cache->get(CacheIntent::read('auth.passwordless', $challengeId));
        if (!is_array($entry)) {
            return null;
        }

        // One-time use challenge
        $this->cache->invalidate(CacheIntent::invalidate('auth.passwordless', $challengeId));

        $expectedEmail = (string) ($entry['email'] ?? '');
        $expectedHash = (string) ($entry['code_hash'] ?? '');
        $providedHash = hash('sha256', $normalizedCode);

        if (!hash_equals($expectedEmail, $normalizedEmail)) {
            return null;
        }
        if (!hash_equals($expectedHash, $providedHash)) {
            return null;
        }

        return $this->issueJwt($normalizedEmail);
    }

    /** @return array<string, mixed> */
    private function issueJwt(string $subject): array
    {
        $secret = trim((string) $this->config->get('security.jwt.secret', ''));
        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET must be configured.');
        }

        $issuedAt = time();
        $ttlSeconds = max(60, (int) $this->config->get('security.jwt.ttl_seconds', 3600));
        $expiresAt = $issuedAt + $ttlSeconds;

        $claims = [
            'sub' => $subject,
            'roles' => ['user'],
            'permissions' => ['contacts:read'],
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $token = $this->encodeJwtHs256($claims, $secret);

        return [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => $ttlSeconds,
            'user' => ['email' => $subject],
        ];
    }

    /** @param array<string, mixed> $claims */
    private function encodeJwtHs256(array $claims, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerSegment = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadSegment = $this->base64UrlEncode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, $secret, true);
        $signatureSegment = $this->base64UrlEncode($signature);

        return $headerSegment . '.' . $payloadSegment . '.' . $signatureSegment;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
```

### 2.3 Add auth controller routes

Create `app/Http/Controllers/Api/PasswordlessAuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\PasswordlessAuthService;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\Security\Authorization\Authorize;

#[RouteGroup(prefix: '/auth/passwordless', version: 'v1', tags: ['Auth'])]
final class PasswordlessAuthController
{
    public function __construct(private PasswordlessAuthService $service)
    {
    }

    #[Route(methods: ['POST'], path: '/start', summary: 'Start passwordless challenge')]
    public function start(Request $request): Response
    {
        $payload = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $email = (string) ($payload['email'] ?? '');
        $result = $this->service->start($email);

        return $this->json(202, $result);
    }

    #[Route(methods: ['POST'], path: '/verify', summary: 'Verify code and return JWT')]
    public function verify(Request $request): Response
    {
        $payload = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $challengeId = (string) ($payload['challenge_id'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $code = (string) ($payload['code'] ?? '');

        $result = $this->service->verify($challengeId, $email, $code);
        if ($result === null) {
            return $this->json(401, ['error' => 'invalid_or_expired_code']);
        }

        return $this->json(200, $result);
    }

    #[Authorize]
    #[Route(methods: ['GET'], path: '/me', summary: 'Current authenticated principal')]
    public function me(RequestContext $ctx): Response
    {
        return $this->json(200, ['auth' => $ctx->getAuth()]);
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): Response
    {
        return new Response(
            $status,
            ['content-type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
        );
    }
}
```

### 2.4 Register service and controller

In `app/AppServiceProvider.php`, register:

```php
use App\Services\PasswordlessAuthService;
use Celeris\Framework\Cache\CacheEngine;
use Celeris\Framework\Config\ConfigRepository;

$services->singleton(
    PasswordlessAuthService::class,
    static fn(ContainerInterface $c): PasswordlessAuthService => new PasswordlessAuthService(
        $c->get(ConfigRepository::class),
        $c->get(CacheEngine::class),
    ),
    [ConfigRepository::class, CacheEngine::class],
);
```

In `public/index.php`, register:

```php
use App\Http\Controllers\Api\PasswordlessAuthController;

$kernel->registerController(PasswordlessAuthController::class, new RouteGroup(prefix: '/api'));
```

## 3. React frontend (Axios only)

Install:

```bash
npm install axios
```

`src/api/tokenStore.ts`:

```ts
const ACCESS_TOKEN_KEY = 'access_token';

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_KEY);
}

export function setAccessToken(token: string): void {
  localStorage.setItem(ACCESS_TOKEN_KEY, token);
}

export function clearAccessToken(): void {
  localStorage.removeItem(ACCESS_TOKEN_KEY);
}
```

`src/api/apiClient.ts`:

```ts
import axios, { AxiosHeaders } from 'axios';
import { clearAccessToken, getAccessToken } from './tokenStore';

export const api = axios.create({ baseURL: '/api' });

api.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (!token) {
    return config;
  }

  if (config.headers instanceof AxiosHeaders) {
    config.headers.set('Authorization', `Bearer ${token}`);
  } else {
    config.headers = { ...(config.headers ?? {}), Authorization: `Bearer ${token}` };
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearAccessToken();
    }
    return Promise.reject(error);
  },
);
```

`src/api/passwordlessService.ts`:

```ts
import { api } from './apiClient';
import { setAccessToken } from './tokenStore';

export type StartPayload = { email: string };
export type StartResponse = { challenge_id: string; expires_in: number; dev_code?: string };
export type VerifyPayload = { challenge_id: string; email: string; code: string };
export type VerifyResponse = { token_type: string; access_token: string; expires_in: number };

export async function startPasswordless(payload: StartPayload): Promise<StartResponse> {
  const { data } = await api.post<StartResponse>('/auth/passwordless/start', payload);
  return data;
}

export async function verifyPasswordless(payload: VerifyPayload): Promise<VerifyResponse> {
  const { data } = await api.post<VerifyResponse>('/auth/passwordless/verify', payload);
  setAccessToken(data.access_token);
  return data;
}

export async function me(): Promise<unknown> {
  const { data } = await api.get('/auth/passwordless/me');
  return data;
}
```

`src/App.tsx`:

```tsx
import { useState } from 'react';
import { me, startPasswordless, verifyPasswordless } from './api/passwordlessService';

export default function App() {
  const [email, setEmail] = useState('demo@example.com');
  const [challengeId, setChallengeId] = useState('');
  const [code, setCode] = useState('');
  const [result, setResult] = useState('');

  async function onStart() {
    try {
      const data = await startPasswordless({ email });
      setChallengeId(data.challenge_id);
      if (data.dev_code) {
        setResult(`Challenge started. Dev code: ${data.dev_code}`);
      } else {
        setResult('Challenge started. Check your email for the code.');
      }
    } catch (error) {
      setResult(`Start failed: ${String(error)}`);
    }
  }

  async function onVerify() {
    try {
      await verifyPasswordless({ challenge_id: challengeId, email, code });
      setResult('Code verified. JWT stored in localStorage.');
    } catch (error) {
      setResult(`Verify failed: ${String(error)}`);
    }
  }

  async function onMe() {
    try {
      const data = await me();
      setResult(JSON.stringify(data, null, 2));
    } catch (error) {
      setResult(`Me request failed: ${String(error)}`);
    }
  }

  return (
    <main style={{ padding: 16 }}>
      <h1>Celeris Passwordless (Axios)</h1>
      <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email" />
      <button onClick={onStart}>Send Code</button>
      <input value={challengeId} onChange={(e) => setChallengeId(e.target.value)} placeholder="challenge_id" />
      <input value={code} onChange={(e) => setCode(e.target.value)} placeholder="code" />
      <button onClick={onVerify}>Verify</button>
      <button onClick={onMe}>/me</button>
      <pre>{result}</pre>
    </main>
  );
}
```

## 4. Quick API test

Start challenge:

```bash
curl -X POST http://localhost/api/auth/passwordless/start \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com"}'
```

Verify challenge:

```bash
curl -X POST http://localhost/api/auth/passwordless/verify \
  -H 'Content-Type: application/json' \
  -d '{"challenge_id":"<id>","email":"demo@example.com","code":"<code>"}'
```

Call protected endpoint:

```bash
curl http://localhost/api/auth/passwordless/me \
  -H 'Authorization: Bearer <jwt>'
```

## 5. Production notes

- Do not return `dev_code` outside local development.
- Replace demo delivery with a real provider (email/SMS).
- Use a shared store (Redis/DB) for challenge records in multi-instance deployments.
- Add max-attempt limits and per-identifier rate limits for challenge endpoints.
