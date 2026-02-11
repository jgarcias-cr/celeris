<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

use Celeris\Framework\Routing\Attribute\Route as RouteAttribute;
use Celeris\Framework\Routing\Attribute\RouteGroup as RouteGroupAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Purpose: load and normalize attribute route loader data from configured sources.
 * How: reads source inputs, validates shape, and returns normalized runtime objects.
 * Used in framework: invoked by routing components when attribute route loader functionality is required.
 */
final class AttributeRouteLoader
{
   /**
    * Create a new instance.
    *
    * @param RouteCollector $collector
    * @return mixed
    */
   public function __construct(private RouteCollector $collector)
   {
   }

   /**
    * Handle collector.
    *
    * @return RouteCollector
    */
   public function collector(): RouteCollector
   {
      return $this->collector;
   }

   /**
    * Handle register controller.
    *
    * @param string $className
    * @param ?RouteGroup $baseGroup
    * @return int
    */
   public function registerController(string $className, ?RouteGroup $baseGroup = null): int
   {
      $reflection = new ReflectionClass($className);
      $classGroup = $this->extractClassGroup($reflection);
      $effectiveGroup = $baseGroup !== null && $classGroup !== null
         ? $baseGroup->merge($classGroup)
         : ($baseGroup ?? $classGroup ?? new RouteGroup());

      $registered = 0;
      $this->collector->group($effectiveGroup, function (RouteCollector $collector) use ($reflection, &$registered): void {
         foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
               /** @var RouteAttribute $instance */
               $instance = $attribute->newInstance();
               $metadata = new RouteMetadata(
                  $instance->name,
                  $instance->summary,
                  $instance->description,
                  $instance->tags,
                  $instance->deprecated,
                  $instance->version,
                  $instance->operationId,
               );

               $collector->add(
                  $instance->methods,
                  $instance->path,
                  [$reflection->getName(), $method->getName()],
                  $instance->middleware,
                  $metadata,
               );
               $registered++;
            }
         }
      });

      return $registered;
   }

   /**
    * Handle extract class group.
    *
    * @param ReflectionClass $reflection
    * @return ?RouteGroup
    */
   private function extractClassGroup(ReflectionClass $reflection): ?RouteGroup
   {
      $attributes = $reflection->getAttributes(RouteGroupAttribute::class);
      if ($attributes === []) {
         return null;
      }

      /** @var RouteGroupAttribute $groupAttribute */
      $groupAttribute = $attributes[0]->newInstance();
      return new RouteGroup(
         $groupAttribute->prefix,
         $groupAttribute->middleware,
         $groupAttribute->version,
         $groupAttribute->tags,
         $groupAttribute->namePrefix,
      );
   }
}




