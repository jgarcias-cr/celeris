<?php

declare(strict_types=1);

namespace Celeris\Framework\Kernel;

use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Config\ConfigSnapshot;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Cache\CacheBootstrap;
use Celeris\Framework\Cache\CacheEngine;
use Celeris\Framework\Cache\Http\HttpCacheHeadersFinalizer;
use Celeris\Framework\Cache\Http\HttpCachePolicy;
use Celeris\Framework\Cache\Invalidation\DeterministicInvalidationEngine;
use Celeris\Framework\Cache\Store\CacheStoreInterface;
use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ProviderRegistry;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\Connection\ConnectionInterface as DbConnectionInterface;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\DatabaseBootstrap;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\Migration\DatabaseMigrationRepository;
use Celeris\Framework\Database\Migration\MigrationRepositoryInterface;
use Celeris\Framework\Database\Migration\MigrationRunner;
use Celeris\Framework\Database\ORM\EntityManager;
use Celeris\Framework\Database\ORM\OrmEngine;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;
use Celeris\Framework\Http\HttpStatus;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseBuilder;
use Celeris\Framework\Http\ResponsePipeline;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\RequestContextContainer;
use Celeris\Framework\Logging\LoggerInterface;
use Celeris\Framework\Logging\LoggingBootstrap;
use Celeris\Framework\Middleware\MiddlewareDispatcher;
use Celeris\Framework\Middleware\Pipeline;
use Celeris\Framework\Notification\NotificationManager;
use Celeris\Framework\Routing\AttributeRouteLoader;
use Celeris\Framework\Routing\OpenApiGenerator;
use Celeris\Framework\Routing\RouteCollector;
use Celeris\Framework\Routing\RouteDefinition;
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Routing\RouteMatch;
use Celeris\Framework\Routing\RouteMetadata;
use Celeris\Framework\Routing\Router;
use Celeris\Framework\Serialization\DtoMapper;
use Celeris\Framework\Serialization\Serializer;
use Celeris\Framework\Security\Auth\AuthEngine;
use Celeris\Framework\Security\Authorization\PolicyEngine;
use Celeris\Framework\Security\Password\PasswordHasher;
use Celeris\Framework\Security\RateLimit\RateLimiter;
use Celeris\Framework\Security\Response\DelegatingSecurityFinalizer;
use Celeris\Framework\Security\SecurityException;
use Celeris\Framework\Security\SecurityKernelGuard;
use Celeris\Framework\Validation\ValidationException;
use Celeris\Framework\Validation\ValidatorEngine;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Purpose: implement kernel behavior for the Kernel subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by kernel components when kernel functionality is required.
 */
final class Kernel implements KernelInterface
{
   private Bootstrap $bootstrap;
   private Pipeline $pipeline;
   private MiddlewareDispatcher $middlewareDispatcher;
   private ResponsePipeline $responsePipeline;
   private RequestContextContainer $contextContainer;
   private Router $router;
   private RouteCollector $routeCollector;
   private AttributeRouteLoader $attributeRouteLoader;
   private OpenApiGenerator $openApiGenerator;
   private bool $booted = false;
   /** @var callable[] */
   private array $requestCleanupHooks = [];
   /** @var callable[] */
   private array $shutdownHooks = [];
   /** @var callable(RequestContext, Request): Response */
   private $entrypoint;
   private ServiceRegistry $coreServices;
   private ProviderRegistry $providerRegistry;
   private ?ConfigLoader $configLoader;
   private string $projectRoot;
   private ConfigRepository $config;
   private Container $container;
   private ?string $configFingerprint = null;
   private bool $hotReloadEnabled;
   private SecurityKernelGuard $securityGuard;
   private bool $securityGuardManagedByConfig;
   private LoggerInterface $logger;
   private bool $loggerManagedByConfig;
   private NotificationManager $notificationManager;
   private bool $notificationManagerManagedByConfig;
   private ValidatorEngine $validator;
   private Serializer $serializer;
   private DtoMapper $dtoMapper;
   private DomainEventDispatcher $domainEventDispatcher;
   private ?ConnectionPool $connectionPool = null;
   private ?DBAL $dbal = null;
   private ?EntityManager $entityManager = null;
   private ?OrmEngine $ormEngine = null;
   private ?MigrationRepositoryInterface $migrationRepository = null;
   private ?MigrationRunner $migrationRunner = null;
   private ?CacheStoreInterface $cacheStore = null;
   private ?DeterministicInvalidationEngine $cacheInvalidation = null;
   private ?CacheEngine $cacheEngine = null;
   private HttpCacheHeadersFinalizer $httpCacheHeadersFinalizer;

   /**
    * @param callable(RequestContext, Request): Response|null $entrypoint
    */
   public function __construct(
      ?Bootstrap $bootstrap = null,
      ?Pipeline $pipeline = null,
      ?ResponsePipeline $responsePipeline = null,
      ?RequestContextContainer $contextContainer = null,
      ?callable $entrypoint = null,
      ?ServiceRegistry $serviceRegistry = null,
      ?ProviderRegistry $providerRegistry = null,
      ?ConfigLoader $configLoader = null,
      bool $hotReloadEnabled = true,
      ?Router $router = null,
      ?RouteCollector $routeCollector = null,
      ?AttributeRouteLoader $attributeRouteLoader = null,
      ?OpenApiGenerator $openApiGenerator = null,
      ?MiddlewareDispatcher $middlewareDispatcher = null,
      ?SecurityKernelGuard $securityGuard = null,
      ?LoggerInterface $logger = null,
      ?NotificationManager $notificationManager = null,
      ?ValidatorEngine $validator = null,
      ?Serializer $serializer = null,
      ?DtoMapper $dtoMapper = null,
      ?DomainEventDispatcher $domainEventDispatcher = null,
      bool $registerBuiltinRoutes = true,
   ) {
      $this->bootstrap = $bootstrap ?? new Bootstrap();
      $this->pipeline = $pipeline ?? new Pipeline();
      $this->middlewareDispatcher = $middlewareDispatcher ?? new MiddlewareDispatcher();
      $this->responsePipeline = $responsePipeline ?? new ResponsePipeline();
      $this->contextContainer = $contextContainer ?? new RequestContextContainer();
      $this->entrypoint = $entrypoint ?? [$this, 'dispatch'];
      $this->coreServices = $serviceRegistry ?? new ServiceRegistry();
      $this->providerRegistry = $providerRegistry ?? new ProviderRegistry();
      $this->configLoader = $configLoader ?? self::defaultConfigLoader();
      $this->projectRoot = self::resolveProjectRoot($this->configLoader);
      $this->config = new ConfigRepository();
      $this->container = new Container();
      $this->hotReloadEnabled = $hotReloadEnabled;
      $this->securityGuardManagedByConfig = $securityGuard === null;
      $this->securityGuard = $securityGuard ?? SecurityKernelGuard::fromConfig($this->config);
      $this->loggerManagedByConfig = $logger === null;
      $this->logger = $logger ?? LoggingBootstrap::fromConfig($this->config, $this->projectRoot);
      $this->notificationManagerManagedByConfig = $notificationManager === null;
      $this->notificationManager = $notificationManager ?? NotificationManager::fromConfig($this->config);
      $this->validator = $validator ?? new ValidatorEngine();
      $this->serializer = $serializer ?? new Serializer();
      $this->dtoMapper = $dtoMapper ?? new DtoMapper($this->validator);
      $this->domainEventDispatcher = $domainEventDispatcher ?? new DomainEventDispatcher();
      $this->router = $router ?? new Router();
      $this->routeCollector = $routeCollector ?? new RouteCollector($this->router);
      $this->attributeRouteLoader = $attributeRouteLoader ?? new AttributeRouteLoader($this->routeCollector);
      $this->openApiGenerator = $openApiGenerator ?? new OpenApiGenerator();
      $this->httpCacheHeadersFinalizer = new HttpCacheHeadersFinalizer(new HttpCachePolicy());
      $this->responsePipeline->add(new DelegatingSecurityFinalizer(
         fn (): \Celeris\Framework\Security\Response\SecurityHeadersFinalizer => $this->securityGuard->headersFinalizer()
      ));
      $this->responsePipeline->add($this->httpCacheHeadersFinalizer);

      $this->registerCoreServices();
      if ($registerBuiltinRoutes) {
         $this->registerBuiltinRoutes();
      }
   }

   /**
    * Handle boot.
    *
    * @return void
    */
   public function boot(): void
   {
      if ($this->booted) {
         return;
      }

      $this->bootstrap->run();
      $this->rebuildContainer($this->configLoader?->snapshot() ?? ConfigSnapshot::empty());
      $this->booted = true;
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request): Response
   {
      if (!$this->booted) {
         $this->boot();
      }

      $requestContainer = $this->container->forRequest($ctx);
      $ctx = $ctx->withAttribute('container', $requestContainer);

      $this->contextContainer->enter($ctx);
      try {
         $entrypoint = $this->entrypoint;
         $response = $this->pipeline->handle(
            $ctx,
            $request,
            function (RequestContext $nextContext, Request $nextRequest) use ($entrypoint): Response {
               $this->contextContainer->replace($nextContext);
               return $entrypoint($nextContext, $nextRequest);
            },
         );
         $finalContext = $this->contextContainer->current() ?? $ctx;
         return $this->responsePipeline->handle($finalContext, $request, $response);
      } catch (ValidationException $exception) {
         $body = $this->serializer->toJson([
            'error' => 'validation_failed',
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
         ]);
         $errorResponse = new Response(
            $exception->status(),
            ['content-type' => 'application/json; charset=utf-8'],
            $body
         );
         $finalContext = $this->contextContainer->current() ?? $ctx;
         return $this->responsePipeline->handle($finalContext, $request, $errorResponse);
      } catch (SecurityException $exception) {
         $errorResponse = new Response(
            $exception->getStatus(),
            array_merge(['content-type' => 'text/plain; charset=utf-8'], $exception->getHeaders()),
            $exception->getMessage()
         );
         $finalContext = $this->contextContainer->current() ?? $ctx;
         return $this->responsePipeline->handle($finalContext, $request, $errorResponse);
      } finally {
         $this->contextContainer->leave();
         $requestContainer->clear();
      }
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      foreach ($this->requestCleanupHooks as $hook) {
         $hook();
      }

      $this->contextContainer->clear();

      if ($this->hotReloadEnabled && $this->configLoader !== null) {
         $changedSnapshot = $this->configLoader->snapshotIfChanged($this->configFingerprint);
         if ($changedSnapshot !== null) {
            $this->rebuildContainer($changedSnapshot);
         }
      }
   }

   /**
    * Handle on request cleanup.
    *
    * @param callable $hook
    * @return void
    */
   public function onRequestCleanup(callable $hook): void
   {
      $this->requestCleanupHooks[] = $hook;
   }

   /**
    * Handle on shutdown.
    *
    * @param callable $hook
    * @return void
    */
   public function onShutdown(callable $hook): void
   {
      $this->shutdownHooks[] = $hook;
   }

   /**
    * Handle shutdown.
    *
    * @return void
    */
   public function shutdown(): void
   {
      foreach ($this->shutdownHooks as $hook) {
         $hook();
      }

      $this->contextContainer->clear();
      $this->container->clearSingletons();
      $this->connectionPool = null;
      $this->dbal = null;
      $this->entityManager = null;
      $this->ormEngine = null;
      $this->migrationRepository = null;
      $this->migrationRunner = null;
      $this->cacheStore = null;
      $this->cacheInvalidation = null;
      $this->cacheEngine = null;
      $this->booted = false;
   }

   /**
    * Get the bootstrap manager.
    *
    * @return BootstrapManager
    */
   public function getBootstrapManager(): BootstrapManager
   {
      return $this->bootstrap;
   }

   /**
    * Handle hot restart.
    *
    * @return void
    */
   public function hotRestart(): void
   {
      $this->shutdown();
      $this->bootstrap->reset();
      $this->boot();
   }

   /**
    * Get the context container.
    *
    * @return RequestContextContainer
    */
   public function getContextContainer(): RequestContextContainer
   {
      return $this->contextContainer;
   }

   /**
    * Get the pipeline.
    *
    * @return Pipeline
    */
   public function getPipeline(): Pipeline
   {
      return $this->pipeline;
   }

   /**
    * Get the middleware dispatcher.
    *
    * @return MiddlewareDispatcher
    */
   public function getMiddlewareDispatcher(): MiddlewareDispatcher
   {
      return $this->middlewareDispatcher;
   }

   /**
    * Get the response pipeline.
    *
    * @return ResponsePipeline
    */
   public function getResponsePipeline(): ResponsePipeline
   {
      return $this->responsePipeline;
   }

   /**
    * Get the security guard.
    *
    * @return SecurityKernelGuard
    */
   public function getSecurityGuard(): SecurityKernelGuard
   {
      return $this->securityGuard;
   }

   /**
    * Set the security guard.
    *
    * @param SecurityKernelGuard $securityGuard
    * @param bool $managedByConfig
    * @return void
    */
   public function setSecurityGuard(SecurityKernelGuard $securityGuard, bool $managedByConfig = false): void
   {
      $this->securityGuard = $securityGuard;
      $this->securityGuardManagedByConfig = $managedByConfig;
   }

   /**
    * Get the notification manager.
    *
    * @return NotificationManager
    */
   public function getNotificationManager(): NotificationManager
   {
      return $this->notificationManager;
   }

   public function getLogger(): LoggerInterface
   {
      return $this->logger;
   }

   public function setLogger(LoggerInterface $logger, bool $managedByConfig = false): void
   {
      $this->logger = $logger;
      $this->loggerManagedByConfig = $managedByConfig;
   }

   /**
    * Set the notification manager.
    *
    * @param NotificationManager $notificationManager
    * @param bool $managedByConfig
    * @return void
    */
   public function setNotificationManager(NotificationManager $notificationManager, bool $managedByConfig = false): void
   {
      $this->notificationManager = $notificationManager;
      $this->notificationManagerManagedByConfig = $managedByConfig;
   }

   /**
    * Get the validator.
    *
    * @return ValidatorEngine
    */
   public function getValidator(): ValidatorEngine
   {
      return $this->validator;
   }

   /**
    * Get the serializer.
    *
    * @return Serializer
    */
   public function getSerializer(): Serializer
   {
      return $this->serializer;
   }

   /**
    * Get the dto mapper.
    *
    * @return DtoMapper
    */
   public function getDtoMapper(): DtoMapper
   {
      return $this->dtoMapper;
   }

   /**
    * Get the domain event dispatcher.
    *
    * @return DomainEventDispatcher
    */
   public function getDomainEventDispatcher(): DomainEventDispatcher
   {
      return $this->domainEventDispatcher;
   }

   /**
    * Get the connection pool.
    *
    * @return ConnectionPool
    */
   public function getConnectionPool(): ConnectionPool
   {
      return $this->databasePool();
   }

   /**
    * Get the dbal.
    *
    * @return DBAL
    */
   public function getDbal(): DBAL
   {
      return $this->dbal();
   }

   /**
    * Get the entity manager.
    *
    * @return EntityManager
    */
   public function getEntityManager(): EntityManager
   {
      return $this->entityManager();
   }

   /**
    * Get the orm engine.
    *
    * @return OrmEngine
    */
   public function getOrmEngine(): OrmEngine
   {
      return $this->ormEngine();
   }

   /**
    * Get the migration runner.
    *
    * @return MigrationRunner
    */
   public function getMigrationRunner(): MigrationRunner
   {
      return $this->migrationRunner();
   }

   /**
    * Get the cache engine.
    *
    * @return CacheEngine
    */
   public function getCacheEngine(): CacheEngine
   {
      return $this->cacheEngine();
   }

   /**
    * Get the cache store.
    *
    * @return CacheStoreInterface
    */
   public function getCacheStore(): CacheStoreInterface
   {
      return $this->cacheStore();
   }

   /**
    * Get the cache invalidation engine.
    *
    * @return DeterministicInvalidationEngine
    */
   public function getCacheInvalidationEngine(): DeterministicInvalidationEngine
   {
      return $this->cacheInvalidation();
   }

   /**
    * Get the http cache headers finalizer.
    *
    * @return HttpCacheHeadersFinalizer
    */
   public function getHttpCacheHeadersFinalizer(): HttpCacheHeadersFinalizer
   {
      return $this->httpCacheHeadersFinalizer;
   }

    /**
     * Get the router.
     *
     * @return Router
     */
    public function getRouter(): Router
    {
       return $this->router;
    }

    /**
     * Handle routes.
     *
     * @return RouteCollector
     */
    public function routes(): RouteCollector
    {
       return $this->routeCollector;
    }

    /**
     * Handle register middleware.
     *
     * @param string $id
     * @param mixed $middleware
     * @return void
     */
    public function registerMiddleware(string $id, mixed $middleware): void
    {
       $this->middlewareDispatcher->register($id, $middleware);
    }

    /**
     * Handle add global middleware.
     *
     * @param string $id
     * @return void
     */
    public function addGlobalMiddleware(string $id): void
    {
       $this->middlewareDispatcher->addGlobal($id);
    }

    /**
     * @param callable(RouteCollector): void $callback
     */
    public function groupRoutes(RouteGroup $group, callable $callback): void
    {
       $this->routeCollector->group($group, $callback);
    }

    /**
     * Get the attribute route loader.
     *
     * @return AttributeRouteLoader
     */
    public function getAttributeRouteLoader(): AttributeRouteLoader
    {
       return $this->attributeRouteLoader;
    }

    /**
     * Get the open api generator.
     *
     * @return OpenApiGenerator
     */
    public function getOpenApiGenerator(): OpenApiGenerator
    {
       return $this->openApiGenerator;
    }

    /**
     * Handle register controller.
     *
     * @param string $className
     * @param ?RouteGroup $group
     * @return int
     */
    public function registerController(string $className, ?RouteGroup $group = null): int
    {
       return $this->attributeRouteLoader->registerController($className, $group);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateOpenApi(?string $title = null, ?string $version = null): array
    {
       $resolvedTitle = $title ?? (string) $this->config->get('app.name', 'Celeris API');
       $resolvedVersion = $version ?? (string) $this->config->get('app.version', '1.0.0');
       return $this->openApiGenerator->generate($this->router, $resolvedTitle, $resolvedVersion);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<int, string>
     */
    public function validateOpenApi(array $document): array
    {
       return $this->openApiGenerator->validate($document);
    }

    /**
     * @param string|null $method
     * @param string|null $path
     * @param string|null $version
     * @return array<int, array<string, mixed>>
     */
    public function inspectMiddleware(?string $method = null, ?string $path = null, ?string $version = null): array
    {
       $pipelineDescriptors = [];
       $position = 1;
       foreach ($this->pipeline->all() as $middleware) {
          $pipelineDescriptors[] = [
             'position' => $position++,
             'scope' => 'global',
             'id' => $middleware::class,
             'type' => $middleware::class,
          ];
       }

       if ($method === null || $path === null) {
          $dispatcher = $this->reindexMiddlewareDescriptors($this->middlewareDispatcher->inspect(), count($pipelineDescriptors));
          return [...$pipelineDescriptors, ...$dispatcher];
       }

       $match = $this->router->resolve($method, $path, $version);
       $dispatcher = $this->reindexMiddlewareDescriptors(
          $this->middlewareDispatcher->inspect($match?->getRoute()->middleware() ?? []),
          count($pipelineDescriptors)
       );

       return [...$pipelineDescriptors, ...$dispatcher];
    }

   /**
    * Get the service container.
    *
    * @return ContainerInterface
    */
   public function getServiceContainer(): ContainerInterface
   {
      if (!$this->booted) {
         $this->boot();
      }

      return $this->container;
   }

   /**
    * Get the service registry.
    *
    * @return ServiceRegistry
    */
   public function getServiceRegistry(): ServiceRegistry
   {
      return $this->coreServices;
   }

   /**
    * Handle register provider.
    *
    * @param ServiceProviderInterface $provider
    * @return void
    */
   public function registerProvider(ServiceProviderInterface $provider): void
   {
      $this->providerRegistry->add($provider);

      if ($this->booted) {
         $this->rebuildContainer($this->configLoader?->snapshot() ?? ConfigSnapshot::empty());
      }
   }

   /**
    * Handle enable hot reload.
    *
    * @param bool $enabled
    * @return void
    */
   public function enableHotReload(bool $enabled = true): void
   {
      $this->hotReloadEnabled = $enabled;
   }

   /**
    * Set the config loader.
    *
    * @param ?ConfigLoader $configLoader
    * @return void
    */
   public function setConfigLoader(?ConfigLoader $configLoader): void
   {
      $this->configLoader = $configLoader;

      if ($this->booted) {
         $this->rebuildContainer($this->configLoader?->snapshot() ?? ConfigSnapshot::empty());
      }
   }

   /**
    * Handle dispatch.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   private function dispatch(RequestContext $ctx, Request $request): Response
   {
      [$ctx, $request] = $this->securityGuard->beforeRouting($ctx, $request);
      $requestedVersion = $this->resolveRequestedVersion($request);
      $match = $this->router->resolve($request->getMethod(), $request->getPath(), $requestedVersion);

      if ($match === null) {
         $allowed = $this->router->allowedMethods($request->getPath(), $requestedVersion);
         if ($allowed !== []) {
            return new Response(
               HttpStatus::METHOD_NOT_ALLOWED,
               [
                  'content-type' => 'text/plain; charset=utf-8',
                  'allow' => implode(', ', $allowed),
               ],
               'Method Not Allowed'
            );
         }

         return new Response(HttpStatus::NOT_FOUND, ['content-type' => 'text/plain; charset=utf-8'], 'Not Found');
      }

      $route = $match->getRoute();
      $routeMetadata = $route->metadata()->toArray();
      $routeMetadata['path_params'] = $match->getParams();
      $ctx = $ctx
         ->withRouteMetadata($routeMetadata)
         ->withAttribute('route_match', $match)
         ->withAttribute('route', $route);
      $this->contextContainer->replace($ctx);
      $this->securityGuard->authorizeRoute($ctx, $route->handler());

      return $this->middlewareDispatcher->dispatch(
         $ctx,
         $request,
         $route->middleware(),
         fn (RequestContext $handlerCtx, Request $handlerRequest): Response
            => $this->invokeRouteHandler($route, $match, $handlerCtx, $handlerRequest),
      );
   }

   /**
    * Handle resolve requested version.
    *
    * @param Request $request
    * @return ?string
    */
   private function resolveRequestedVersion(Request $request): ?string
   {
      $version = $request->getHeader('x-api-version');
      if ($version !== null && trim($version) !== '') {
         return trim($version);
      }

      $queryVersion = $request->getQueryParam('api_version');
      if (is_string($queryVersion) && trim($queryVersion) !== '') {
         return trim($queryVersion);
      }

      return null;
   }

   /**
    * @param array<int, array<string, mixed>> $descriptors
    * @return array<int, array<string, mixed>>
    */
   private function reindexMiddlewareDescriptors(array $descriptors, int $offset): array
   {
      $indexed = [];
      foreach ($descriptors as $descriptor) {
         $descriptor['position'] = ((int) ($descriptor['position'] ?? 0)) + $offset;
         $indexed[] = $descriptor;
      }

      return $indexed;
   }

   /**
    * Handle invoke route handler.
    *
    * @param RouteDefinition $route
    * @param RouteMatch $match
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   private function invokeRouteHandler(
      RouteDefinition $route,
      RouteMatch $match,
      RequestContext $ctx,
      Request $request
   ): Response {
      $callable = $this->resolveHandlerCallable($route->handler(), $ctx);
      $result = $this->invokeCallable($callable, $ctx, $request, $match->getParams());

      if ($result instanceof Response) {
         return $result;
      }

      if (is_array($result) || is_object($result)) {
         return new Response(
            HttpStatus::OK,
            ['content-type' => 'application/json; charset=utf-8'],
            $this->serializer->toJson($result)
         );
      }

      return new Response(
         HttpStatus::OK,
         ['content-type' => 'text/plain; charset=utf-8'],
         (string) $result
      );
   }

   /**
    * Handle resolve handler callable.
    *
    * @param mixed $handler
    * @param RequestContext $ctx
    * @return callable
    */
   private function resolveHandlerCallable(mixed $handler, RequestContext $ctx): callable
   {
      if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && is_string($handler[1])) {
         $instance = $this->resolveClassFromContext($handler[0], $ctx);
         return [$instance, $handler[1]];
      }

      if (is_callable($handler)) {
         if (is_array($handler) && isset($handler[0]) && is_string($handler[0])) {
            $className = $handler[0];
            $method = $handler[1] ?? null;
            if (!is_string($method)) {
               throw new RuntimeException('Route handler array must contain class and method.');
            }
            $instance = $this->resolveClassFromContext($className, $ctx);
            return [$instance, $method];
         }

         return $handler;
      }

      if (is_string($handler) && str_contains($handler, '@')) {
         [$className, $method] = explode('@', $handler, 2);
         $instance = $this->resolveClassFromContext($className, $ctx);
         return [$instance, $method];
      }

      throw new RuntimeException('Route handler is not callable.');
   }

   /**
    * Handle resolve class from context.
    *
    * @param string $className
    * @param RequestContext $ctx
    * @return object
    */
   private function resolveClassFromContext(string $className, RequestContext $ctx): object
   {
      $container = $ctx->getAttribute('container');
      if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') && $container->has($className)) {
         $resolved = $container->get($className);
         if (is_object($resolved)) {
            return $resolved;
         }
      }

      return new $className();
   }

   /**
    * @param array<string, string> $pathParams
    */
   private function invokeCallable(callable $callable, RequestContext $ctx, Request $request, array $pathParams): mixed
   {
      $reflection = is_array($callable)
         ? new ReflectionMethod($callable[0], (string) $callable[1])
         : new ReflectionFunction(\Closure::fromCallable($callable));

      $args = [];
      foreach ($reflection->getParameters() as $parameter) {
         $args[] = $this->resolveHandlerArgument($parameter, $ctx, $request, $pathParams);
      }

      return $callable(...$args);
   }

   /**
    * @param array<string, string> $pathParams
    */
   private function resolveHandlerArgument(
      ReflectionParameter $parameter,
      RequestContext $ctx,
      Request $request,
      array $pathParams
   ): mixed {
      $type = $parameter->getType();
      if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
         $className = $type->getName();
         if ($className === RequestContext::class) {
            return $ctx;
         }
         if ($className === Request::class) {
            return $request;
         }

         $container = $ctx->getAttribute('container');
         if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') && $container->has($className)) {
            return $container->get($className);
         }

         if ($this->dtoMapper->supports($className)) {
            $payload = $request->getMethod() === 'GET'
               ? $request->getQueryParams()
               : $request->getParsedBody();

            if (!is_array($payload)) {
               $payload = $request->getQueryParams();
            }

            /** @var array<string, mixed> $payload */
            return $this->dtoMapper->map($className, $payload, true);
         }
      }

      $name = $parameter->getName();
      if ($name === 'params') {
         return $pathParams;
      }
      if (array_key_exists($name, $pathParams)) {
         return $this->castPathParam($pathParams[$name], $type);
      }

      if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
         return $pathParams;
      }

      if ($parameter->isDefaultValueAvailable()) {
         return $parameter->getDefaultValue();
      }
      if ($parameter->allowsNull()) {
         return null;
      }

      throw new RuntimeException(sprintf('Unable to resolve argument "$%s" for route handler.', $name));
   }

   /**
    * Handle cast path param.
    *
    * @param string $value
    * @param ?\ReflectionType $type
    * @return mixed
    */
   private function castPathParam(string $value, ?\ReflectionType $type): mixed
   {
      if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
         return $value;
      }

      return match ($type->getName()) {
         'int' => (int) $value,
         'float' => (float) $value,
         'bool' => in_array(strtolower($value), ['1', 'true', 'yes'], true),
         'string' => $value,
         default => $value,
      };
   }

   /**
    * Handle register core services.
    *
    * @return void
    */
   private function registerCoreServices(): void
   {
      $this->coreServices->singleton(
         ConfigRepository::class,
         fn (ContainerInterface $container): ConfigRepository => $this->config,
         [],
         true
      );
      $this->coreServices->singleton(
         'config',
         fn (ContainerInterface $container): ConfigRepository => $this->config,
         [ConfigRepository::class],
         true
      );
      $this->coreServices->singleton(
         ConfigLoader::class,
         fn (ContainerInterface $container): ?ConfigLoader => $this->configLoader,
         [],
         true
      );
      $this->coreServices->singleton(
         BootstrapManager::class,
         fn (ContainerInterface $container): BootstrapManager => $this->bootstrap,
         [],
         true
      );
      $this->coreServices->transient(
         ResponseBuilder::class,
         static fn (ContainerInterface $container): ResponseBuilder => new ResponseBuilder(),
         [],
         true
      );
      $this->coreServices->singleton(
         ResponsePipeline::class,
         fn (ContainerInterface $container): ResponsePipeline => $this->responsePipeline,
         [],
         true
      );
      $this->coreServices->singleton(
         Router::class,
         fn (ContainerInterface $container): Router => $this->router,
         [],
         true
      );
      $this->coreServices->singleton(
         RouteCollector::class,
         fn (ContainerInterface $container): RouteCollector => $this->routeCollector,
         [Router::class],
         true
      );
      $this->coreServices->singleton(
         AttributeRouteLoader::class,
         fn (ContainerInterface $container): AttributeRouteLoader => $this->attributeRouteLoader,
         [RouteCollector::class],
         true
      );
      $this->coreServices->singleton(
         OpenApiGenerator::class,
         fn (ContainerInterface $container): OpenApiGenerator => $this->openApiGenerator,
         [Router::class],
         true
      );
      $this->coreServices->singleton(
         MiddlewareDispatcher::class,
         fn (ContainerInterface $container): MiddlewareDispatcher => $this->middlewareDispatcher,
         [],
         true
      );
      $this->coreServices->singleton(
         SecurityKernelGuard::class,
         fn (ContainerInterface $container): SecurityKernelGuard => $this->securityGuard,
         [],
         true
      );
      $this->coreServices->singleton(
         LoggerInterface::class,
         fn (ContainerInterface $container): LoggerInterface => $this->logger,
         [],
         true
      );
      $this->coreServices->singleton(
         'logger',
         fn (ContainerInterface $container): LoggerInterface => $this->logger,
         [LoggerInterface::class],
         true
      );
      $this->coreServices->singleton(
         NotificationManager::class,
         fn (ContainerInterface $container): NotificationManager => $this->notificationManager,
         [],
         true
      );
      $this->coreServices->singleton(
         AuthEngine::class,
         fn (ContainerInterface $container): AuthEngine => $this->securityGuard->authEngine(),
         [SecurityKernelGuard::class],
         true
      );
      $this->coreServices->singleton(
         PolicyEngine::class,
         fn (ContainerInterface $container): PolicyEngine => $this->securityGuard->policyEngine(),
         [SecurityKernelGuard::class],
         true
      );
      $this->coreServices->singleton(
         RateLimiter::class,
         fn (ContainerInterface $container): RateLimiter => $this->securityGuard->rateLimiter(),
         [SecurityKernelGuard::class],
         true
      );
      $this->coreServices->singleton(
         PasswordHasher::class,
         fn (ContainerInterface $container): PasswordHasher => $this->securityGuard->passwordHasher(),
         [SecurityKernelGuard::class],
         true
      );
      $this->coreServices->singleton(
         ValidatorEngine::class,
         fn (ContainerInterface $container): ValidatorEngine => $this->validator,
         [],
         true
      );
      $this->coreServices->singleton(
         Serializer::class,
         fn (ContainerInterface $container): Serializer => $this->serializer,
         [],
         true
      );
      $this->coreServices->singleton(
         DtoMapper::class,
         fn (ContainerInterface $container): DtoMapper => $this->dtoMapper,
         [ValidatorEngine::class],
         true
      );
      $this->coreServices->singleton(
         DomainEventDispatcher::class,
         fn (ContainerInterface $container): DomainEventDispatcher => $this->domainEventDispatcher,
         [],
         true
      );
      $this->coreServices->singleton(
         ConnectionPool::class,
         fn (ContainerInterface $container): ConnectionPool => $this->databasePool(),
         [ConfigRepository::class],
         true
      );
      $this->coreServices->singleton(
         DBAL::class,
         fn (ContainerInterface $container): DBAL => $this->dbal(),
         [ConnectionPool::class],
         true
      );
      $this->coreServices->singleton(
         DbConnectionInterface::class,
         fn (ContainerInterface $container): DbConnectionInterface => $this->dbal()->connection($this->defaultDatabaseConnectionName()),
         [DBAL::class],
         true
      );
      $this->coreServices->singleton(
         EntityManager::class,
         fn (ContainerInterface $container): EntityManager => $this->entityManager(),
         [DbConnectionInterface::class, DomainEventDispatcher::class],
         true
      );
      $this->coreServices->singleton(
         OrmEngine::class,
         fn (ContainerInterface $container): OrmEngine => $this->ormEngine(),
         [EntityManager::class],
         true
      );
      $this->coreServices->singleton(
         MigrationRepositoryInterface::class,
         fn (ContainerInterface $container): MigrationRepositoryInterface => $this->migrationRepository(),
         [DbConnectionInterface::class],
         true
      );
      $this->coreServices->singleton(
         MigrationRunner::class,
         fn (ContainerInterface $container): MigrationRunner => $this->migrationRunner(),
         [DbConnectionInterface::class, MigrationRepositoryInterface::class],
         true
      );
      $this->coreServices->singleton(
         CacheStoreInterface::class,
         fn (ContainerInterface $container): CacheStoreInterface => $this->cacheStore(),
         [ConfigRepository::class],
         true
      );
      $this->coreServices->singleton(
         DeterministicInvalidationEngine::class,
         fn (ContainerInterface $container): DeterministicInvalidationEngine => $this->cacheInvalidation(),
         [CacheStoreInterface::class],
         true
      );
      $this->coreServices->singleton(
         CacheEngine::class,
         fn (ContainerInterface $container): CacheEngine => $this->cacheEngine(),
         [CacheStoreInterface::class, DeterministicInvalidationEngine::class],
         true
      );
      $this->coreServices->singleton(
         HttpCacheHeadersFinalizer::class,
         fn (ContainerInterface $container): HttpCacheHeadersFinalizer => $this->httpCacheHeadersFinalizer,
         [],
         true
      );
      $this->coreServices->singleton(
         HttpCachePolicy::class,
         static fn (ContainerInterface $container): HttpCachePolicy => new HttpCachePolicy(),
         [],
         true
      );
      $this->coreServices->singleton(
         ContainerInterface::class,
         static fn (ContainerInterface $container): ContainerInterface => $container,
         [],
         true
      );
      $this->coreServices->singleton(
         KernelInterface::class,
         fn (ContainerInterface $container): KernelInterface => $this,
         [],
         true
      );
      $this->coreServices->singleton(
         self::class,
         fn (ContainerInterface $container): self => $this,
         [KernelInterface::class],
         true
      );
   }

   /**
    * Handle database pool.
    *
    * @return ConnectionPool
    */
   private function databasePool(): ConnectionPool
   {
      if ($this->connectionPool instanceof ConnectionPool) {
         return $this->connectionPool;
      }

      $this->connectionPool = DatabaseBootstrap::poolFromConfig($this->config);
      return $this->connectionPool;
   }

   /**
    * Handle dbal.
    *
    * @return DBAL
    */
   private function dbal(): DBAL
   {
      if ($this->dbal instanceof DBAL) {
         return $this->dbal;
      }

      $this->dbal = new DBAL($this->databasePool());
      return $this->dbal;
   }

   /**
    * Handle entity manager.
    *
    * @return EntityManager
    */
   private function entityManager(): EntityManager
   {
      if ($this->entityManager instanceof EntityManager) {
         return $this->entityManager;
      }

      $connection = $this->dbal()->connection($this->defaultDatabaseConnectionName());
      $this->entityManager = new EntityManager($connection, null, null, $this->domainEventDispatcher);
      return $this->entityManager;
   }

   /**
    * Handle orm engine.
    *
    * @return OrmEngine
    */
   private function ormEngine(): OrmEngine
   {
      if ($this->ormEngine instanceof OrmEngine) {
         return $this->ormEngine;
      }

      $this->ormEngine = new OrmEngine(
         $this->dbal()->connection($this->defaultDatabaseConnectionName()),
         null,
         $this->entityManager(),
      );
      return $this->ormEngine;
   }

   /**
    * Handle migration repository.
    *
    * @return MigrationRepositoryInterface
    */
   private function migrationRepository(): MigrationRepositoryInterface
   {
      if ($this->migrationRepository instanceof MigrationRepositoryInterface) {
         return $this->migrationRepository;
      }

      $this->migrationRepository = new DatabaseMigrationRepository(
         $this->dbal()->connection($this->defaultDatabaseConnectionName()),
      );
      return $this->migrationRepository;
   }

   /**
    * Handle migration runner.
    *
    * @return MigrationRunner
    */
   private function migrationRunner(): MigrationRunner
   {
      if ($this->migrationRunner instanceof MigrationRunner) {
         return $this->migrationRunner;
      }

      $this->migrationRunner = new MigrationRunner(
         $this->dbal()->connection($this->defaultDatabaseConnectionName()),
         $this->migrationRepository(),
      );
      return $this->migrationRunner;
   }

   /**
    * Handle default database connection name.
    *
    * @return string
    */
   private function defaultDatabaseConnectionName(): string
   {
      return DatabaseBootstrap::defaultConnectionName($this->config);
   }

   /**
    * Handle cache store.
    *
    * @return CacheStoreInterface
    */
   private function cacheStore(): CacheStoreInterface
   {
      if ($this->cacheStore instanceof CacheStoreInterface) {
         return $this->cacheStore;
      }

      $this->cacheStore = CacheBootstrap::storeFromConfig($this->config);
      return $this->cacheStore;
   }

   /**
    * Handle cache invalidation.
    *
    * @return DeterministicInvalidationEngine
    */
   private function cacheInvalidation(): DeterministicInvalidationEngine
   {
      if ($this->cacheInvalidation instanceof DeterministicInvalidationEngine) {
         return $this->cacheInvalidation;
      }

      $this->cacheInvalidation = new DeterministicInvalidationEngine();
      return $this->cacheInvalidation;
   }

   /**
    * Handle cache engine.
    *
    * @return CacheEngine
    */
   private function cacheEngine(): CacheEngine
   {
      if ($this->cacheEngine instanceof CacheEngine) {
         return $this->cacheEngine;
      }

      $this->cacheEngine = new CacheEngine($this->cacheStore(), $this->cacheInvalidation());
      return $this->cacheEngine;
   }

   /**
    * Handle rebuild container.
    *
    * @param ConfigSnapshot $snapshot
    * @return void
    */
   private function rebuildContainer(ConfigSnapshot $snapshot): void
   {
      $this->config = new ConfigRepository(
         $snapshot->getItems(),
         $snapshot->getEnvironment(),
         $snapshot->getSecrets(),
         $snapshot->getFingerprint(),
         $snapshot->getLoadedAt(),
      );
      if ($this->securityGuardManagedByConfig) {
         $this->securityGuard = SecurityKernelGuard::fromConfig($this->config);
      }
      if ($this->loggerManagedByConfig) {
         $this->logger = LoggingBootstrap::fromConfig($this->config, $this->projectRoot);
      }
      if ($this->notificationManagerManagedByConfig) {
         $this->notificationManager = NotificationManager::fromConfig($this->config);
      }
      $this->connectionPool = null;
      $this->dbal = null;
      $this->entityManager = null;
      $this->ormEngine = null;
      $this->migrationRepository = null;
      $this->migrationRunner = null;
      $this->cacheStore = null;
      $this->cacheInvalidation = null;
      $this->cacheEngine = null;

      $services = $this->coreServices->copy();
      $this->providerRegistry->registerProviders($services);

      $this->container = new Container($services->all());
      $this->container->validateCircularDependencies();
      $this->providerRegistry->bootProviders($this->container);
      $this->configFingerprint = $snapshot->getFingerprint();
   }

   /**
    * Handle default config loader.
    *
    * @return ConfigLoader
    */
   private static function defaultConfigLoader(): ConfigLoader
   {
      $basePath = self::defaultBasePath();
      $configPath = $basePath . '/config';
      $envPath = $basePath . '/.env';
      $secretsPath = $basePath . '/secrets';

      return new ConfigLoader(
         $configPath,
         new EnvironmentLoader(
            is_file($envPath) ? $envPath : null,
            is_dir($secretsPath) ? $secretsPath : null,
            false,
            false,
         ),
      );
   }

   /**
    * Handle default base path.
    *
    * @return string
    */
   private static function defaultBasePath(): string
   {
      $workspaceRoot = dirname(__DIR__, 5);
      if (is_dir($workspaceRoot)) {
         return $workspaceRoot;
      }

      return dirname(__DIR__, 3);
   }

   private static function resolveProjectRoot(?ConfigLoader $configLoader): string
   {
      if ($configLoader instanceof ConfigLoader) {
         $dir = rtrim($configLoader->configDirectory(), '/\\');
         if ($dir !== '') {
            return dirname($dir);
         }
      }

      return self::defaultBasePath();
   }

   /**
    * Handle register builtin routes.
    *
    * @return void
    */
   private function registerBuiltinRoutes(): void
   {
      $this->routeCollector->get(
         '/',
         function (RequestContext $ctx, Request $request): Response {
            $appName = (string) $this->config->get('app.name', 'Celeris Framework');
            return new Response(
               200,
               ['content-type' => 'text/html; charset=utf-8'],
               sprintf('<h1>%s — core</h1>', htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            );
         },
         [],
         new RouteMetadata(
            name: 'core.home',
            summary: 'Framework landing page',
            tags: ['Core']
         ),
      );

      $this->routeCollector->get(
         '/health',
         function (RequestContext $ctx, Request $request): Response {
            $appName = (string) $this->config->get('app.name', 'Celeris Framework');
            return new Response(
               200,
               ['content-type' => 'application/json; charset=utf-8'],
               (string) json_encode(['status' => 'ok', 'app' => $appName], JSON_UNESCAPED_UNICODE)
            );
         },
         [],
         new RouteMetadata(
            name: 'core.health',
            summary: 'Health check endpoint',
            tags: ['Core']
         ),
      );
   }
}
