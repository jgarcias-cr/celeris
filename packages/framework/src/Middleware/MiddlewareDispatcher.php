<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Closure;
use InvalidArgumentException;
use ReflectionFunction;

/**
 * Route middleware dispatcher events/messages to registered handlers.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class MiddlewareDispatcher
{
   /** @var array<string, MiddlewareInterface|callable|class-string> */
   private array $registry = [];
   /** @var array<int, string> */
   private array $globalOrder = [];

   /**
    * Handle register.
    *
    * @param string $id
    * @param MiddlewareInterface|callable|string $middleware
    * @return void
    */
   public function register(string $id, MiddlewareInterface|callable|string $middleware): void
   {
      $clean = trim($id);
      if ($clean === '') {
         throw new InvalidArgumentException('Middleware id cannot be empty.');
      }

      $this->registry[$clean] = $middleware;
   }

   /**
    * Handle add global.
    *
    * @param string $id
    * @return void
    */
   public function addGlobal(string $id): void
   {
      $clean = trim($id);
      if ($clean === '') {
         throw new InvalidArgumentException('Global middleware id cannot be empty.');
      }

      $this->globalOrder[] = $clean;
   }

   /**
    * Handle dispatch.
    * @param array<int, string> $routeMiddleware
    * @param callable(RequestContext, Request): Response $final
    */
   public function dispatch(RequestContext $ctx, Request $request, array $routeMiddleware, callable $final): Response
   {
      $orderedIds = [...$this->globalOrder, ...$routeMiddleware];
      if ($orderedIds === []) {
         return $final($ctx, $request);
      }

      $resolved = [];
      foreach ($orderedIds as $id) {
         $resolved[] = $this->resolveMiddleware($id, $ctx);
      }

      $runner = function (RequestContext $currentCtx, Request $currentRequest) use (&$resolved, $final, &$runner): Response {
         $middleware = array_shift($resolved);
         if ($middleware === null) {
            return $final($currentCtx, $currentRequest);
         }

         return $middleware->handle(
            $currentCtx,
            $currentRequest,
            static fn (RequestContext $nextCtx, Request $nextReq): Response => $runner($nextCtx, $nextReq),
         );
      };

      return $runner($ctx, $request);
   }

   /**
    * Handle inspect.
    * @param array<int, string> $routeMiddleware
    * @return array<int, array<string, mixed>>
    */
   public function inspect(array $routeMiddleware = []): array
   {
      $result = [];
      $position = 1;

      foreach ($this->globalOrder as $id) {
         $result[] = [
            'position' => $position++,
            'scope' => 'global',
            'id' => $id,
            'type' => $this->middlewareType($id),
         ];
      }
      foreach ($routeMiddleware as $id) {
         $result[] = [
            'position' => $position++,
            'scope' => 'route',
            'id' => $id,
            'type' => $this->middlewareType($id),
         ];
      }

      return $result;
   }

   /**
    * Handle global order.
    *
    * @return array<int, string>
    */
   public function globalOrder(): array
   {
      return $this->globalOrder;
   }

   /**
    * Handle middleware type.
    *
    * @param string $id
    * @return string
    */
   private function middlewareType(string $id): string
   {
      $entry = $this->registry[$id] ?? null;
      if ($entry instanceof MiddlewareInterface) {
         return $entry::class;
      }
      if (is_string($entry)) {
         return $entry;
      }
      if (is_callable($entry)) {
         return 'callable';
      }

      return 'unregistered';
   }

   /**
    * Handle resolve middleware.
    *
    * @param string $id
    * @param RequestContext $ctx
    * @return MiddlewareInterface
    */
   private function resolveMiddleware(string $id, RequestContext $ctx): MiddlewareInterface
   {
      $clean = trim($id);
      if ($clean === '') {
         throw new InvalidArgumentException('Middleware id cannot be empty.');
      }

      $entry = $this->registry[$clean] ?? null;
      if ($entry === null && class_exists($clean)) {
         $entry = $clean;
      }
      if ($entry === null) {
         throw new InvalidArgumentException(sprintf('Middleware "%s" is not registered.', $clean));
      }

      if ($entry instanceof MiddlewareInterface) {
         return $entry;
      }

      if (is_callable($entry)) {
         $reflection = new ReflectionFunction(Closure::fromCallable($entry));
         if ($reflection->getNumberOfParameters() >= 2) {
            return new CallableMiddleware($entry);
         }

         $resolved = $entry($ctx);
         if ($resolved instanceof MiddlewareInterface) {
            return $resolved;
         }
         if (is_callable($resolved)) {
            return new CallableMiddleware($resolved);
         }
         throw new InvalidArgumentException(sprintf('Middleware factory "%s" did not return a valid middleware.', $clean));
      }

      if (is_string($entry)) {
         return $this->resolveClassMiddleware($entry, $ctx);
      }

      throw new InvalidArgumentException(sprintf('Unsupported middleware entry for "%s".', $clean));
   }

   /**
    * Handle resolve class middleware.
    *
    * @param string $class
    * @param RequestContext $ctx
    * @return MiddlewareInterface
    */
   private function resolveClassMiddleware(string $class, RequestContext $ctx): MiddlewareInterface
   {
      $container = $ctx->getAttribute('container');
      if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get') && $container->has($class)) {
         $instance = $container->get($class);
      } else {
         $instance = new $class();
      }

      if (!$instance instanceof MiddlewareInterface) {
         throw new InvalidArgumentException(sprintf('Middleware class "%s" must implement MiddlewareInterface.', $class));
      }

      return $instance;
   }
}



