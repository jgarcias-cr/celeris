<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Implement jwt token strategy behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class JwtTokenStrategy implements AuthStrategyInterface
{
   /** @var array<int, string> */
   private array $allowedAlgorithms;

   /**
    * @param array<int, string> $allowedAlgorithms
    */
   public function __construct(
      private string $secret,
      array $allowedAlgorithms = ['HS256'],
      private int $leewaySeconds = 30,
      private ?string $issuer = null,
      private ?string $audience = null,
   ) {
      $this->allowedAlgorithms = array_values(array_unique(array_map(
         static fn (mixed $algorithm): string => strtoupper(trim((string) $algorithm)),
         $allowedAlgorithms
      )));
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'jwt';
   }

   /**
    * Determine whether supports.
    *
    * @param Request $request
    * @return bool
    */
   public function supports(Request $request): bool
   {
      $token = CredentialExtractor::bearerToken($request);
      if ($token === null) {
         return false;
      }

      return substr_count($token, '.') === 2;
   }

   /**
    * Handle authenticate.
    *
    * @param RequestContext $context
    * @param Request $request
    * @return AuthResult
    */
   public function authenticate(RequestContext $context, Request $request): AuthResult
   {
      $token = CredentialExtractor::bearerToken($request);
      if ($token === null) {
         return AuthResult::rejected('Missing bearer token.', ['www-authenticate' => 'Bearer']);
      }
      if (trim($this->secret) === '') {
         return AuthResult::rejected('JWT secret is not configured.', ['www-authenticate' => 'Bearer']);
      }

      $parts = explode('.', $token);
      if (count($parts) !== 3) {
         return AuthResult::rejected('Invalid JWT format.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
      $header = self::decodeJsonSegment($encodedHeader);
      $payload = self::decodeJsonSegment($encodedPayload);
      if ($header === null || $payload === null) {
         return AuthResult::rejected('Malformed JWT payload.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      $algorithm = strtoupper((string) ($header['alg'] ?? ''));
      if ($algorithm === '' || !in_array($algorithm, $this->allowedAlgorithms, true)) {
         return AuthResult::rejected('Unsupported JWT algorithm.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      if ($algorithm !== 'HS256') {
         return AuthResult::rejected('JWT algorithm is not supported by this strategy.');
      }

      $expectedSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true);
      $providedSignature = self::decodeBase64Url($encodedSignature);
      if (!is_string($providedSignature) || !hash_equals($expectedSignature, $providedSignature)) {
         return AuthResult::rejected('Invalid JWT signature.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      $now = microtime(true);
      if (isset($payload['nbf']) && is_numeric($payload['nbf']) && $now + $this->leewaySeconds < (float) $payload['nbf']) {
         return AuthResult::rejected('JWT not active yet.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }
      if (isset($payload['exp']) && is_numeric($payload['exp']) && $now - $this->leewaySeconds >= (float) $payload['exp']) {
         return AuthResult::rejected('JWT expired.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }
      if (
         $this->issuer !== null
         && isset($payload['iss'])
         && (string) $payload['iss'] !== $this->issuer
      ) {
         return AuthResult::rejected('Invalid JWT issuer.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }
      if (
         $this->audience !== null
         && isset($payload['aud'])
         && !$this->matchesAudience($payload['aud'], $this->audience)
      ) {
         return AuthResult::rejected('Invalid JWT audience.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      $subject = trim((string) ($payload['sub'] ?? ''));
      if ($subject === '') {
         return AuthResult::rejected('JWT subject claim is required.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      $roles = self::stringListFromMixed($payload['roles'] ?? []);
      $permissions = self::stringListFromMixed($payload['permissions'] ?? []);
      if (isset($payload['scope']) && is_string($payload['scope'])) {
         $scopeParts = preg_split('/\s+/', trim($payload['scope']));
         if (is_array($scopeParts)) {
            $permissions = array_values(array_unique([...$permissions, ...self::stringListFromMixed($scopeParts)]));
         }
      }

      $tokenId = isset($payload['jti']) ? trim((string) $payload['jti']) : null;
      if ($tokenId === '') {
         $tokenId = null;
      }
      if ($tokenId === null) {
         $tokenId = hash('sha256', $token);
      }

      $identity = new Identity(
         $subject,
         $roles,
         $permissions,
         ['jwt_claims' => $payload],
         microtime(true),
      );

      return AuthResult::authenticated($identity, $this->name(), $tokenId);
   }

   /**
    * @return array<string, mixed>|null
    */
   private static function decodeJsonSegment(string $segment): ?array
   {
      $decoded = self::decodeBase64Url($segment);
      if (!is_string($decoded) || $decoded === '') {
         return null;
      }

      $payload = json_decode($decoded, true);
      return is_array($payload) ? $payload : null;
   }

   /**
    * Handle decode base64 url.
    *
    * @param string $value
    * @return ?string
    */
   private static function decodeBase64Url(string $value): ?string
   {
      $encoded = strtr($value, '-_', '+/');
      $padding = strlen($encoded) % 4;
      if ($padding > 0) {
         $encoded .= str_repeat('=', 4 - $padding);
      }

      $decoded = base64_decode($encoded, true);
      return is_string($decoded) ? $decoded : null;
   }

   /**
    * Handle matches audience.
    *
    * @param mixed $candidate
    * @param string $expected
    * @return bool
    */
   private function matchesAudience(mixed $candidate, string $expected): bool
   {
      if (is_string($candidate)) {
         return $candidate === $expected;
      }
      if (is_array($candidate)) {
         foreach ($candidate as $item) {
            if ((string) $item === $expected) {
               return true;
            }
         }
      }

      return false;
   }

   /**
    * @return array<int, string>
    */
   private static function stringListFromMixed(mixed $value): array
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
}



