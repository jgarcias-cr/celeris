<?php

declare(strict_types=1);

namespace Celeris\Framework\Http\Cors;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use InvalidArgumentException;

/**
 * Resolves CORS config and applies policy decisions for a request.
 */
final class CorsPolicy
{
   /**
    * @return array<string, mixed>
    */
   public function evaluate(RequestContext $ctx, Request $request): array
   {
      $config = $this->resolveConfig($ctx);
      $pathMatched = $this->pathMatches($request->getPath(), $config['paths']);
      $origin = trim((string) $request->getHeader('origin', ''));
      $preflight = $this->isPreflightRequest($request);

      $decision = [
         'path_matched' => $pathMatched,
         'origin' => $origin,
         'preflight' => $preflight,
         'cors_allowed' => false,
         'preflight_allowed' => false,
         'allow_origin' => null,
         'supports_credentials' => (bool) $config['supports_credentials'],
         'allowed_methods' => $config['allowed_methods'],
         'allowed_headers' => [],
         'exposed_headers' => $config['exposed_headers'],
         'max_age' => $config['max_age'],
         'vary' => [],
      ];

      if ($origin === '' || !$pathMatched) {
         return $decision;
      }

      $allowOrigin = $this->resolveAllowedOrigin(
         $origin,
         $config['allowed_origins'],
         (bool) $config['supports_credentials'],
      );

      if ($allowOrigin === null) {
         return $decision;
      }

      $decision['cors_allowed'] = true;
      $decision['allow_origin'] = $allowOrigin;
      $decision['vary'] = $allowOrigin === '*' ? [] : ['Origin'];

      if (!$preflight) {
         return $decision;
      }

      $requestedMethod = strtoupper(trim((string) $request->getHeader('access-control-request-method', '')));
      if ($requestedMethod === '' || !in_array($requestedMethod, $config['allowed_methods'], true)) {
         return $decision;
      }

      $requestedHeaders = $this->parseHeaderList($request->getHeader('access-control-request-headers'));
      [$headersAllowed, $allowedHeaders] = $this->resolveAllowedHeaders($requestedHeaders, $config['allowed_headers']);
      if (!$headersAllowed) {
         return $decision;
      }

      $decision['preflight_allowed'] = true;
      $decision['allowed_headers'] = $allowedHeaders;
      $decision['vary'] = $this->mergeTokens(
         $decision['vary'],
         ['Access-Control-Request-Method', 'Access-Control-Request-Headers'],
      );

      return $decision;
   }

   public function applyActualHeaders(Response $response, array $decision): Response
   {
      if (($decision['cors_allowed'] ?? false) !== true || !is_string($decision['allow_origin'] ?? null)) {
         return $response;
      }

      $updated = $this->withHeaderIfMissing($response, 'access-control-allow-origin', $decision['allow_origin']);
      if (($decision['supports_credentials'] ?? false) === true) {
         $updated = $this->withHeaderIfMissing($updated, 'access-control-allow-credentials', 'true');
      }

      $exposedHeaders = is_array($decision['exposed_headers'] ?? null) ? $decision['exposed_headers'] : [];
      if ($exposedHeaders !== []) {
         $updated = $this->withHeaderIfMissing($updated, 'access-control-expose-headers', implode(', ', $exposedHeaders));
      }

      return $this->withVary($updated, is_array($decision['vary'] ?? null) ? $decision['vary'] : []);
   }

   public function applyPreflightHeaders(Response $response, array $decision): Response
   {
      $updated = $this->applyActualHeaders($response, $decision);
      if (($decision['preflight_allowed'] ?? false) !== true) {
         return $updated;
      }

      $allowedMethods = is_array($decision['allowed_methods'] ?? null) ? $decision['allowed_methods'] : [];
      if ($allowedMethods !== []) {
         $updated = $this->withHeaderIfMissing($updated, 'access-control-allow-methods', implode(', ', $allowedMethods));
      }

      $allowedHeaders = is_array($decision['allowed_headers'] ?? null) ? $decision['allowed_headers'] : [];
      if ($allowedHeaders !== []) {
         $updated = $this->withHeaderIfMissing($updated, 'access-control-allow-headers', implode(', ', $allowedHeaders));
      }

      $maxAge = $decision['max_age'] ?? null;
      if (is_int($maxAge) && $maxAge > 0) {
         $updated = $this->withHeaderIfMissing($updated, 'access-control-max-age', (string) $maxAge);
      }

      return $updated;
   }

   /**
    * @return array<string, mixed>
    */
   private function resolveConfig(RequestContext $ctx): array
   {
      $raw = [];
      $container = $ctx->getAttribute('container');
      if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') && $container->has(ConfigRepository::class)) {
         $config = $container->get(ConfigRepository::class);
         if ($config instanceof ConfigRepository) {
            $resolved = $config->get('cors', []);
            if (is_array($resolved)) {
               $raw = $resolved;
            }
         }
      }

      $paths = $this->normalizeStringList($raw['paths'] ?? ['/api/*']);
      $allowedOrigins = $this->normalizeStringList($raw['allowed_origins'] ?? []);
      $allowedMethods = $this->normalizeMethods($raw['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
      $allowedHeaders = $this->normalizeStringList($raw['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Version', 'X-Csrf-Token']);
      $exposedHeaders = $this->normalizeStringList($raw['exposed_headers'] ?? []);
      $supportsCredentials = (bool) ($raw['supports_credentials'] ?? false);
      $maxAge = $this->normalizeMaxAge($raw['max_age'] ?? 600);

      if ($supportsCredentials && in_array('*', $allowedOrigins, true)) {
         throw new InvalidArgumentException('CORS does not allow wildcard origins when credentials support is enabled.');
      }

      return [
         'paths' => $paths,
         'allowed_origins' => $allowedOrigins,
         'allowed_methods' => $allowedMethods,
         'allowed_headers' => $allowedHeaders,
         'exposed_headers' => $exposedHeaders,
         'supports_credentials' => $supportsCredentials,
         'max_age' => $maxAge,
      ];
   }

   private function isPreflightRequest(Request $request): bool
   {
      if ($request->getMethod() !== 'OPTIONS') {
         return false;
      }

      return trim((string) $request->getHeader('origin', '')) !== ''
         && trim((string) $request->getHeader('access-control-request-method', '')) !== '';
   }

   /**
    * @param array<int, string> $patterns
    */
   private function pathMatches(string $path, array $patterns): bool
   {
      if ($patterns === []) {
         return true;
      }

      foreach ($patterns as $pattern) {
         $quoted = preg_quote($pattern, '/');
         $regex = '/^' . str_replace('\*', '.*', $quoted) . '$/';
         if (preg_match($regex, $path) === 1) {
            return true;
         }
      }

      return false;
   }

   /**
    * @param array<int, string> $allowedOrigins
    */
   private function resolveAllowedOrigin(string $origin, array $allowedOrigins, bool $supportsCredentials): ?string
   {
      if ($allowedOrigins === []) {
         return null;
      }

      if (in_array('*', $allowedOrigins, true)) {
         return $supportsCredentials ? null : '*';
      }

      return in_array($origin, $allowedOrigins, true) ? $origin : null;
   }

   /**
    * @param array<int, string> $requestedHeaders
    * @param array<int, string> $configuredHeaders
    * @return array{0: bool, 1: array<int, string>}
    */
   private function resolveAllowedHeaders(array $requestedHeaders, array $configuredHeaders): array
   {
      if ($requestedHeaders === []) {
         return [true, $configuredHeaders];
      }

      if (in_array('*', $configuredHeaders, true)) {
         return [true, $requestedHeaders];
      }

      $allowedLookup = [];
      foreach ($configuredHeaders as $header) {
         $allowedLookup[strtolower($header)] = true;
      }

      foreach ($requestedHeaders as $header) {
         if (!isset($allowedLookup[strtolower($header)])) {
            return [false, []];
         }
      }

      return [true, $configuredHeaders];
   }

   /**
    * @return array<int, string>
    */
   private function parseHeaderList(?string $value): array
   {
      if ($value === null || trim($value) === '') {
         return [];
      }

      return $this->normalizeStringList(explode(',', $value));
   }

   /**
    * @param mixed $value
    * @return array<int, string>
    */
   private function normalizeStringList(mixed $value): array
   {
      $values = is_array($value) ? $value : [$value];
      $normalized = [];

      foreach ($values as $item) {
         $clean = trim((string) $item);
         if ($clean === '') {
            continue;
         }
         if (!in_array($clean, $normalized, true)) {
            $normalized[] = $clean;
         }
      }

      return $normalized;
   }

   /**
    * @param mixed $value
    * @return array<int, string>
    */
   private function normalizeMethods(mixed $value): array
   {
      $normalized = [];
      foreach ($this->normalizeStringList($value) as $method) {
         $clean = strtoupper($method);
         if (!in_array($clean, $normalized, true)) {
            $normalized[] = $clean;
         }
      }

      return $normalized;
   }

   private function normalizeMaxAge(mixed $value): int
   {
      if (is_int($value)) {
         return max(0, $value);
      }
      if (is_numeric($value)) {
         return max(0, (int) $value);
      }

      return 0;
   }

   /**
    * @param array<int, string> $values
    */
   private function withVary(Response $response, array $values): Response
   {
      $merged = $this->mergeTokens($this->parseHeaderList($response->getHeader('vary')), $values);
      if ($merged === []) {
         return $response;
      }

      return $response->withHeader('vary', implode(', ', $merged));
   }

   private function withHeaderIfMissing(Response $response, string $name, string $value): Response
   {
      if ($response->getHeader($name) !== null) {
         return $response;
      }

      return $response->withHeader($name, $value);
   }

   /**
    * @param array<int, string> $existing
    * @param array<int, string> $incoming
    * @return array<int, string>
    */
   private function mergeTokens(array $existing, array $incoming): array
   {
      $merged = $existing;
      $lookup = [];

      foreach ($existing as $token) {
         $lookup[strtolower($token)] = true;
      }

      foreach ($incoming as $token) {
         $clean = trim($token);
         if ($clean === '') {
            continue;
         }

         $key = strtolower($clean);
         if (isset($lookup[$key])) {
            continue;
         }

         $lookup[$key] = true;
         $merged[] = $clean;
      }

      return $merged;
   }
}
