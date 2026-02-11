<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Purpose: implement open api generator behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when open api generator functionality is required.
 */
final class OpenApiGenerator
{
   /**
    * @return array<string, mixed>
    */
   public function generate(Router $router, string $title = 'Celeris API', string $version = '1.0.0'): array
   {
      $paths = [];

      foreach ($router->allRoutes() as $route) {
         $path = $route->path();
         $metadata = $route->metadata();
         $pathParameters = $this->extractPathParameters($path);

         foreach ($route->methods() as $method) {
            $operationId = $metadata->operationId()
               ?? strtolower($method) . '_' . trim(str_replace(['/', '{', '}'], ['_', '', ''], $path), '_');

            $operation = [
               'operationId' => $operationId,
               'responses' => [
                  '200' => ['description' => 'Success'],
               ],
            ];

            if ($metadata->summary() !== null) {
               $operation['summary'] = $metadata->summary();
            }
            if ($metadata->description() !== null) {
               $operation['description'] = $metadata->description();
            }
            if ($metadata->tags() !== []) {
               $operation['tags'] = $metadata->tags();
            }
            if ($metadata->deprecated()) {
               $operation['deprecated'] = true;
            }
            if ($metadata->version() !== null) {
               $operation['x-api-version'] = $metadata->version();
            }
            if ($pathParameters !== []) {
               $operation['parameters'] = $pathParameters;
            }

            $paths[$path][strtolower($method)] = $operation;
         }
      }

      ksort($paths);

      return [
         'openapi' => '3.1.0',
         'info' => [
            'title' => $title,
            'version' => $version,
         ],
         'paths' => $paths,
      ];
   }

   /**
    * Convert to json.
    *
    * @param Router $router
    * @param string $title
    * @param string $version
    * @return string
    */
   public function toJson(Router $router, string $title = 'Celeris API', string $version = '1.0.0'): string
   {
      return (string) json_encode($this->generate($router, $title, $version), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
   }

   /**
    * @param array<string, mixed> $document
    * @return array<int, string>
    */
   public function validate(array $document): array
   {
      $errors = [];

      if (!isset($document['openapi']) || !is_string($document['openapi'])) {
         $errors[] = 'Missing or invalid openapi version.';
      }
      if (!isset($document['info']) || !is_array($document['info'])) {
         $errors[] = 'Missing info section.';
      } else {
         if (!isset($document['info']['title']) || !is_string($document['info']['title'])) {
            $errors[] = 'Missing info.title.';
         }
         if (!isset($document['info']['version']) || !is_string($document['info']['version'])) {
            $errors[] = 'Missing info.version.';
         }
      }

      if (!isset($document['paths']) || !is_array($document['paths'])) {
         $errors[] = 'Missing paths section.';
      } else {
         foreach ($document['paths'] as $path => $operations) {
            if (!is_string($path) || !str_starts_with($path, '/')) {
               $errors[] = sprintf('Invalid path key "%s".', (string) $path);
               continue;
            }

            if (!is_array($operations) || $operations === []) {
               $errors[] = sprintf('Path "%s" has no operations.', $path);
               continue;
            }

            foreach ($operations as $method => $operation) {
               if (!is_array($operation)) {
                  $errors[] = sprintf('Invalid operation for %s %s.', strtoupper((string) $method), $path);
                  continue;
               }
               if (!isset($operation['responses']) || !is_array($operation['responses']) || $operation['responses'] === []) {
                  $errors[] = sprintf('Operation %s %s has no responses.', strtoupper((string) $method), $path);
               }
            }
         }
      }

      return $errors;
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   private function extractPathParameters(string $path): array
   {
      preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches);
      $parameters = [];
      foreach ($matches[1] ?? [] as $name) {
         $parameters[] = [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
         ];
      }

      return $parameters;
   }
}




