<?php

declare(strict_types=1);

namespace App\Services;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Security\Password\PasswordHasher;
use RuntimeException;

final class AuthService
{
   /**
    * bcrypt hash for demo password: "password123"
    */
   private const DEFAULT_DEMO_PASSWORD_HASH = '$2y$12$9GK/vlsIdSj5KeClYZyTu.ZDgGUdh0PsyNprCZsHo37ykuPQ4AWfS';

   public function __construct(
      private ConfigRepository $config,
      private PasswordHasher $hasher,
   ) {
   }

   /**
    * @return array<string, mixed>|null
    */
   public function attemptLogin(string $username, string $password): ?array
   {
      $username = trim($username);
      if ($username === '' || $password === '') {
         return null;
      }

      $demoUser = $this->resolveDemoUser();
      if (!hash_equals($demoUser['username'], $username)) {
         return null;
      }

      if (!$this->hasher->verify($password, $demoUser['password_hash'])) {
         return null;
      }

      $secret = trim((string) $this->config->get('security.jwt.secret', ''));
      if ($secret === '') {
         throw new RuntimeException('JWT_SECRET must be configured before issuing access tokens.');
      }

      $issuedAt = time();
      $ttlSeconds = max(60, (int) $this->config->get('security.jwt.ttl_seconds', 3600));
      $expiresAt = $issuedAt + $ttlSeconds;

      $claims = [
         'sub' => $demoUser['username'],
         'roles' => $demoUser['roles'],
         'permissions' => $demoUser['permissions'],
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
         'user' => [
            'username' => $demoUser['username'],
            'roles' => $demoUser['roles'],
            'permissions' => $demoUser['permissions'],
         ],
      ];
   }

   /**
    * @return array{username: string, password_hash: string, roles: array<int, string>, permissions: array<int, string>}
    */
   private function resolveDemoUser(): array
   {
      $username = trim((string) $this->config->get('security.demo_user.username', 'demo'));
      if ($username === '') {
         $username = 'demo';
      }

      $passwordHash = trim((string) $this->config->get('security.demo_user.password_hash', self::DEFAULT_DEMO_PASSWORD_HASH));
      if ($passwordHash === '') {
         $passwordHash = self::DEFAULT_DEMO_PASSWORD_HASH;
      }

      $roles = $this->normalizeList($this->config->get('security.demo_user.roles', ['user']));
      if ($roles === []) {
         $roles = ['user'];
      }

      $permissions = $this->normalizeList($this->config->get('security.demo_user.permissions', ['contacts:read', 'contacts:write']));
      if ($permissions === []) {
         $permissions = ['contacts:read', 'contacts:write'];
      }

      return [
         'username' => $username,
         'password_hash' => $passwordHash,
         'roles' => $roles,
         'permissions' => $permissions,
      ];
   }

   /**
    * @param mixed $value
    * @return array<int, string>
    */
   private function normalizeList(mixed $value): array
   {
      if (is_string($value)) {
         $value = explode(',', $value);
      }

      if (!is_array($value)) {
         return [];
      }

      $normalized = [];
      foreach ($value as $item) {
         $clean = trim((string) $item);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }

   /**
    * @param array<string, mixed> $claims
    */
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
