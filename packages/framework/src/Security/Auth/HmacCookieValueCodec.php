<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Signs opaque cookie payloads so session identifiers cannot be tampered with.
 */
final class HmacCookieValueCodec implements CookieValueCodecInterface
{
   public function __construct(
      private string $key,
      private string $version = 'v1',
   ) {
   }

   public function encode(string $value): string
   {
      $payload = self::base64UrlEncode($value);
      $signature = self::base64UrlEncode(hash_hmac('sha256', $this->version . '.' . $payload, $this->key, true));

      return $this->version . '.' . $payload . '.' . $signature;
   }

   public function decode(string $value): ?string
   {
      $parts = explode('.', $value, 3);
      if (count($parts) !== 3) {
         return null;
      }

      [$version, $payload, $providedSignature] = $parts;
      if ($version !== $this->version || $payload === '' || $providedSignature === '') {
         return null;
      }

      $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', $version . '.' . $payload, $this->key, true));
      if (!hash_equals($expectedSignature, $providedSignature)) {
         return null;
      }

      $decoded = self::base64UrlDecode($payload);
      if ($decoded === null || trim($decoded) === '') {
         return null;
      }

      return $decoded;
   }

   private static function base64UrlEncode(string $value): string
   {
      return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
   }

   private static function base64UrlDecode(string $value): ?string
   {
      $normalized = strtr($value, '-_', '+/');
      $padding = strlen($normalized) % 4;
      if ($padding !== 0) {
         $normalized .= str_repeat('=', 4 - $padding);
      }

      $decoded = base64_decode($normalized, true);
      if (!is_string($decoded)) {
         return null;
      }

      return $decoded;
   }
}

