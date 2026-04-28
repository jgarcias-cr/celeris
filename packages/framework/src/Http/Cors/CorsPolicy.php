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
    * Evaluate the CORS policy for the given request and context, returning a decision array with the results.
    *
    * The returned array will contain keys such as:
    * - 'path_matched': bool
    * - 'origin': string
    * - 'preflight': bool
    * - 'cors_allowed': bool
    * - 'preflight_allowed': bool
    * - 'allow_origin': string|null
    * - 'supports_credentials': bool
    * - 'allowed_methods': array<int, string>
    * - 'allowed_headers': array<int, string>
    * - 'exposed_headers': array<int, string>
    * - 'max_age': int
    * - 'vary': array<int, string>
    * The exact structure and keys may vary based on the implementation, but it should provide enough information to apply CORS headers to the response.
    *
    * @param RequestContext $ctx The request context, which may contain attributes such as the container for resolving configuration.
    * @param Request $request The incoming HTTP request to evaluate against the CORS policy.
    * @throws InvalidArgumentException If the CORS configuration is invalid (e.g., wildcard origins with credentials support).
    *
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

   /**
    * Apply CORS headers to a normal response using the computed policy decision.
    *
    * @param Response $response The original HTTP response to which CORS headers should be applied.
    * @param array<string, mixed> $decision The CORS policy decision array computed by the evaluate() method, containing keys such as 'cors_allowed', 'allow_origin', 'supports_credentials', 'exposed_headers', and 'vary'.
    * @return Response A new HTTP response instance with the appropriate CORS headers applied based on the decision.
    */
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

   /**
    * Apply preflight-specific CORS headers to the response.
    */
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
    * Resolve CORS configuration from the request context, typically by accessing a configuration repository from the container. This method should return a normalized configuration array with keys such as 'paths', 'allowed_origins', 'allowed_methods', 'allowed_headers', 'exposed_headers', 'supports_credentials', and 'max_age'.
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

   /**
    * Determine whether the request is a CORS preflight probe.
    */
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
    * Determine the appropriate Access-Control-Allow-Origin value based on the request origin and the configured allowed origins. This method should return the allowed origin to use in the response, or null if the origin is not allowed. It should also handle wildcard origins and credentials support according to CORS rules.
    *
    * @param string $origin The Origin header value from the incoming request.
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
    * Determine the appropriate Access-Control-Allow-Headers value based on the requested headers and the configured allowed headers. This method should return a tuple where the first element is a boolean indicating whether the requested headers are allowed, and the second element is the list of headers to include in the Access-Control-Allow-Headers response header if allowed.
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
    * Parse a comma-separated header value into an array of trimmed strings. If the input is null or empty, an empty array is returned.
    *
    * @param string|null $value The raw header value to parse, which may be null or a comma-separated string.
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
    * Normalize a value that may be a string or an array of strings into a deduplicated array of trimmed strings. Empty strings are filtered out.
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
    * Normalize a value that may be a string or an array of strings into a deduplicated array of uppercase strings, suitable for HTTP methods. Empty strings are filtered out.
    *
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

   /**
    * Normalize a configured max-age value into a non-negative integer.
    */
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
    * Add values to the Vary header, merging with any existing values and ensuring no duplicates. If the resulting list of Vary tokens is empty, the header will not be added.
    * The Vary header is important for CORS responses to ensure that caches properly differentiate responses based on the
    * Origin and other relevant request headers.
    *
    * @param Response $response The original HTTP response to which the Vary header should be added.
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

   /**
    * Add a response header only when it is not already present.
    */
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
