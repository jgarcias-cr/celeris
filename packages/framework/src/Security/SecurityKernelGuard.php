<?php

declare(strict_types=1);

namespace Celeris\Framework\Security;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\Auth\ApiTokenStrategy;
use Celeris\Framework\Security\Auth\AuthEngine;
use Celeris\Framework\Security\Auth\AuthStrategyInterface;
use Celeris\Framework\Security\Auth\CookieSessionStrategy;
use Celeris\Framework\Security\Auth\HmacCookieValueCodec;
use Celeris\Framework\Security\Auth\InMemoryApiTokenStore;
use Celeris\Framework\Security\Auth\InMemoryOpaqueTokenStore;
use Celeris\Framework\Security\Auth\InMemorySessionStore;
use Celeris\Framework\Security\Auth\InMemoryTokenRevocationStore;
use Celeris\Framework\Security\Auth\JwtTokenStrategy;
use Celeris\Framework\Security\Auth\MutualTlsStrategy;
use Celeris\Framework\Security\Auth\OpaqueTokenStrategy;
use Celeris\Framework\Security\Auth\StoredCredential;
use Celeris\Framework\Security\Authorization\PolicyEngine;
use Celeris\Framework\Security\Csrf\CsrfProtector;
use Celeris\Framework\Security\Input\InputNormalizer;
use Celeris\Framework\Security\Input\RequestValidator;
use Celeris\Framework\Security\Input\SqlInjectionGuard;
use Celeris\Framework\Security\Password\PasswordHasher;
use Celeris\Framework\Security\RateLimit\RateLimiter;
use Celeris\Framework\Security\Response\SecurityHeadersFinalizer;

/**
 * Implement security kernel guard behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SecurityKernelGuard
{
   /**
    * Create a new instance.
    *
    * @param AuthEngine $authEngine
    * @param PolicyEngine $policyEngine
    * @param CsrfProtector $csrfProtector
    * @param InputNormalizer $inputNormalizer
    * @param RequestValidator $requestValidator
    * @param SqlInjectionGuard $sqlGuard
    * @param RateLimiter $rateLimiter
    * @param SecurityHeadersFinalizer $headersFinalizer
    * @param PasswordHasher $passwordHasher
    * @return mixed
    */
   public function __construct(
      private AuthEngine $authEngine,
      private PolicyEngine $policyEngine,
      private CsrfProtector $csrfProtector,
      private InputNormalizer $inputNormalizer,
      private RequestValidator $requestValidator,
      private SqlInjectionGuard $sqlGuard,
      private RateLimiter $rateLimiter,
      private SecurityHeadersFinalizer $headersFinalizer,
      private PasswordHasher $passwordHasher,
   ) {
   }

   /**
    * @return array{RequestContext, Request}
    */
   public function beforeRouting(RequestContext $ctx, Request $request): array
   {
      $normalizedRequest = $this->inputNormalizer->normalize($request);
      $this->requestValidator->validate($normalizedRequest);
      $this->sqlGuard->inspect($normalizedRequest);
      $this->rateLimiter->enforce($ctx, $normalizedRequest);
      $securedContext = $this->authEngine->authenticate($ctx, $normalizedRequest);
      $this->csrfProtector->enforce($securedContext, $normalizedRequest);

      return [$securedContext, $normalizedRequest];
   }

   /**
    * Handle authorize route.
    *
    * @param RequestContext $ctx
    * @param mixed $handler
    * @return void
    */
   public function authorizeRoute(RequestContext $ctx, mixed $handler): void
   {
      $this->policyEngine->authorize($ctx, $handler);
   }

   /**
    * Handle auth engine.
    *
    * @return AuthEngine
    */
   public function authEngine(): AuthEngine
   {
      return $this->authEngine;
   }

   /**
    * Handle policy engine.
    *
    * @return PolicyEngine
    */
   public function policyEngine(): PolicyEngine
   {
      return $this->policyEngine;
   }

   /**
    * Handle rate limiter.
    *
    * @return RateLimiter
    */
   public function rateLimiter(): RateLimiter
   {
      return $this->rateLimiter;
   }

   /**
    * Handle headers finalizer.
    *
    * @return SecurityHeadersFinalizer
    */
   public function headersFinalizer(): SecurityHeadersFinalizer
   {
      return $this->headersFinalizer;
   }

   /**
    * Handle password hasher.
    *
    * @return PasswordHasher
    */
   public function passwordHasher(): PasswordHasher
   {
      return $this->passwordHasher;
   }

   /**
    * Create an instance from config.
    *
    * @param ConfigRepository $config
    * @return self
    */
   public static function fromConfig(ConfigRepository $config): self
   {
      $opaqueStore = new InMemoryOpaqueTokenStore(
         self::credentialsFromMap((array) $config->get('security.tokens.opaque', []))
      );
      $apiStore = new InMemoryApiTokenStore(
         self::credentialsFromMap((array) $config->get('security.tokens.api', []))
      );
      $sessionStore = new InMemorySessionStore(
         self::credentialsFromMap((array) $config->get('security.sessions', []))
      );

      $revocationStore = new InMemoryTokenRevocationStore();
      $revokedTokenIds = (array) $config->get('security.revoked_tokens', []);
      foreach ($revokedTokenIds as $tokenId) {
         $clean = trim((string) $tokenId);
         if ($clean !== '') {
            $revocationStore->revoke($clean);
         }
      }

      $strategies = self::buildStrategies($config, $opaqueStore, $apiStore, $sessionStore);
      $authEngine = new AuthEngine($strategies, $revocationStore);
      $policyEngine = new PolicyEngine();

      $csrfProtector = new CsrfProtector(
         (bool) $config->get('security.csrf.enabled', true),
         (array) $config->get('security.csrf.methods', ['POST', 'PUT', 'PATCH', 'DELETE']),
         (string) $config->get('security.csrf.cookie', 'csrf_token'),
         (string) $config->get('security.csrf.header', 'x-csrf-token'),
         (string) $config->get('security.csrf.field', '_csrf'),
         (string) $config->get('security.csrf.session_cookie', 'session_id'),
      );

      $normalizer = new InputNormalizer();
      $validator = new RequestValidator(
         (int) $config->get('security.request.max_body_bytes', 1048576),
         (int) $config->get('security.request.max_header_value_length', 8192),
      );
      $sqlGuard = new SqlInjectionGuard();
      $rateLimiter = new RateLimiter(
         (int) $config->get('security.rate_limit.limit', 120),
         (int) $config->get('security.rate_limit.window_seconds', 60),
         (int) $config->get('security.rate_limit.burst', 0),
      );
      $headersFinalizer = new SecurityHeadersFinalizer(
         (array) $config->get('security.headers', [])
      );
      $passwordHasher = new PasswordHasher(
         (string) $config->get('security.password.algorithm', ''),
         (array) $config->get('security.password.options', []),
      );

      return new self(
         $authEngine,
         $policyEngine,
         $csrfProtector,
         $normalizer,
         $validator,
         $sqlGuard,
         $rateLimiter,
         $headersFinalizer,
         $passwordHasher,
      );
   }

   /**
    * @return array<int, AuthStrategyInterface>
    */
   private static function buildStrategies(
      ConfigRepository $config,
      InMemoryOpaqueTokenStore $opaqueStore,
      InMemoryApiTokenStore $apiStore,
      InMemorySessionStore $sessionStore,
   ): array {
      $strategies = [];

      if ((bool) $config->get('security.auth.jwt.enabled', true)) {
         $strategies[] = new JwtTokenStrategy(
            (string) $config->get('security.jwt.secret', $config->secret('JWT_SECRET', '') ?? ''),
            (array) $config->get('security.jwt.algorithms', ['HS256']),
            (int) $config->get('security.jwt.leeway_seconds', 30),
            self::nullableString($config->get('security.jwt.issuer')),
            self::nullableString($config->get('security.jwt.audience')),
         );
      }

      if ((bool) $config->get('security.auth.opaque.enabled', true)) {
         $strategies[] = new OpaqueTokenStrategy($opaqueStore);
      }

      if ((bool) $config->get('security.auth.api_token.enabled', true)) {
         $strategies[] = new ApiTokenStrategy(
            $apiStore,
            (string) $config->get('security.api_token.header', 'x-api-key'),
            (string) $config->get('security.api_token.query', 'api_key'),
         );
      }

      if ((bool) $config->get('security.auth.cookie_session.enabled', true)) {
         $cookieSigningEnabled = (bool) $config->get('security.auth.cookie_session.signing.enabled', false);
         $cookieSigner = null;
         $allowUnsignedFallback = (bool) $config->get('security.auth.cookie_session.signing.allow_unsigned_fallback', false);
         if ($cookieSigningEnabled) {
            $cookieSigningKey = trim((string) $config->get(
               'security.auth.cookie_session.signing.key',
               $config->secret('APP_KEY', (string) $config->get('app.key', '')) ?? ''
            ));
            if ($cookieSigningKey === '') {
               throw new SecurityException('Cookie session signing is enabled, but no signing key is configured.');
            }

            $cookieSigner = new HmacCookieValueCodec($cookieSigningKey);
         }

         $strategies[] = new CookieSessionStrategy(
            $sessionStore,
            (string) $config->get('security.auth.cookie_session.cookie', 'session_id'),
            $cookieSigner,
            $allowUnsignedFallback,
         );
      }

      if ((bool) $config->get('security.auth.mtls.enabled', true)) {
         $strategies[] = new MutualTlsStrategy();
      }

      return $strategies;
   }

   /**
    * @param array<string, mixed> $map
    * @return array<string, StoredCredential>
    */
   private static function credentialsFromMap(array $map): array
   {
      $credentials = [];
      foreach ($map as $token => $spec) {
         $credentialToken = trim((string) $token);
         $data = is_array($spec) ? $spec : [];
         if (is_int($token)) {
            $candidateToken = trim((string) ($data['token'] ?? ''));
            $credentialToken = $candidateToken;
         }
         if ($credentialToken === '') {
            continue;
         }

         if (is_string($spec)) {
            $data = ['subject' => $spec];
         }

         $subject = trim((string) ($data['subject'] ?? ''));
         if ($subject === '') {
            continue;
         }

         $expiresAt = null;
         if (isset($data['expires_at']) && is_numeric($data['expires_at'])) {
            $expiresAt = (float) $data['expires_at'];
         }

         $credentials[$credentialToken] = new StoredCredential(
            $subject,
            self::stringList((array) ($data['roles'] ?? [])),
            self::stringList((array) ($data['permissions'] ?? [])),
            is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            $expiresAt,
            self::nullableString($data['token_id'] ?? null),
         );
      }

      return $credentials;
   }

   /**
    * @return array<int, string>
    */
   private static function stringList(array $items): array
   {
      $normalized = [];
      foreach ($items as $item) {
         $clean = trim((string) $item);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }

   /**
    * Handle nullable string.
    *
    * @param mixed $value
    * @return ?string
    */
   private static function nullableString(mixed $value): ?string
   {
      if (!is_string($value)) {
         return null;
      }

      $clean = trim($value);
      return $clean === '' ? null : $clean;
   }
}

