<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Web;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\ToolingException;

/**
 * Purpose: implement developer ui controller behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when developer ui controller functionality is required.
 */
final class DeveloperUiController
{
   /**
    * Create a new instance.
    *
    * @param GeneratorEngine $generatorEngine
    * @param DependencyGraphBuilder $dependencyGraphBuilder
    * @param ArchitectureDecisionValidator $architectureValidator
    * @param string $projectRoot
    * @param string $routePrefix
    * @param string $namespaceRoot
    * @return mixed
    */
   public function __construct(
      private GeneratorEngine $generatorEngine,
      private DependencyGraphBuilder $dependencyGraphBuilder,
      private ArchitectureDecisionValidator $architectureValidator,
      private string $projectRoot,
      private string $routePrefix = '/__dev/tooling',
      private string $namespaceRoot = 'App',
   ) {
   }

   /**
    * Invoke the handler.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function __invoke(RequestContext $ctx, Request $request): Response
   {
      return $this->handle($ctx, $request);
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
      $subPath = $this->subPath($request->getPath());

      if ($subPath === '/graph') {
         $graph = $this->dependencyGraphBuilder->buildModuleGraph();
         return $this->json(200, $graph->toArray());
      }

      if ($subPath === '/validate') {
         $graph = $this->dependencyGraphBuilder->buildModuleGraph();
         $report = $this->architectureValidator->validate($graph);
         return $this->json(200, $report->toArray());
      }

      if ($subPath === '/generate/preview') {
         return $this->previewResponse($request);
      }

      return $this->dashboardResponse($request);
   }

   /**
    * Handle dashboard response.
    *
    * @param Request $request
    * @return Response
    */
   private function dashboardResponse(Request $request): Response
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      $report = $this->architectureValidator->validate($graph);
      $generatorList = $this->generatorEngine->list();

      $generator = $this->stringParam($request, 'generator');
      $name = $this->stringParam($request, 'name');
      $module = $this->stringParam($request, 'module') ?? 'Generated';
      $previewRows = [];

      if ($generator !== null && $name !== null) {
         try {
            $previewRows = array_map(
               static fn ($preview): array => $preview->toArray(),
               $this->generatorEngine->preview($generator, new GenerationRequest(
                  basePath: $this->projectRoot,
                  name: $name,
                  module: $module,
                  namespaceRoot: $this->namespaceRoot,
               ))
            );
         } catch (ToolingException $exception) {
            $previewRows = [[
               'path' => 'error',
               'exists' => false,
               'diff' => $exception->getMessage(),
               'contents' => '',
            ]];
         }
      }

      $payload = [
         'graph' => $graph->toArray(),
         'architecture' => $report->toArray(),
         'generators' => $generatorList,
         'preview' => $previewRows,
      ];

      $statusColor = $report->isValid() ? '#0d9488' : '#dc2626';
      $statusText = $report->isValid() ? 'VALID' : 'VIOLATIONS';
      $json = htmlspecialchars((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

      $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Celeris Tooling</title>
<style>
:root {
  --sand: #f6efe3;
  --ink: #1f2937;
  --accent: #ef4444;
  --teal: #0d9488;
  --panel: #fffdf8;
  --line: #d6cec3;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: "Space Grotesk", "Segoe UI", sans-serif;
  color: var(--ink);
  background: radial-gradient(circle at 12% 18%, #f9d9bf 0%, transparent 35%), linear-gradient(140deg, #f6efe3 0%, #fefaf1 100%);
}
main {
  max-width: 1040px;
  margin: 2rem auto;
  padding: 0 1rem 2rem;
}
.card {
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 8px 18px rgba(40, 25, 14, 0.06);
}
pre {
  margin: 0;
  overflow-x: auto;
  white-space: pre-wrap;
  font-family: "JetBrains Mono", "Cascadia Code", monospace;
  font-size: 0.83rem;
  line-height: 1.4;
}
.badge {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.04em;
  font-size: 0.75rem;
  background: {$statusColor};
}
label { display: inline-block; font-size: 0.78rem; font-weight: 600; margin-right: 0.25rem; }
input {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 0.35rem 0.45rem;
  font: inherit;
  margin-right: 0.6rem;
}
button {
  border: 0;
  border-radius: 8px;
  background: var(--accent);
  color: #fff;
  padding: 0.45rem 0.75rem;
  font: inherit;
  font-weight: 700;
}
small { color: #6b7280; }
</style>
</head>
<body>
<main>
  <section class="card">
    <h1 style="margin-top:0">Celeris Tooling Platform</h1>
    <span class="badge">ARCH {$statusText}</span>
    <p><small>Live architecture validation, module dependency mapping, and generator preview diffs.</small></p>
  </section>
  <section class="card">
    <h2 style="margin-top:0">Generator Preview</h2>
    <form method="get" action="{$this->routePrefix}">
      <label for="generator">Generator</label><input id="generator" name="generator" value="{$this->escape((string) ($generator ?? 'controller'))}">
      <label for="name">Name</label><input id="name" name="name" value="{$this->escape((string) ($name ?? 'Sample'))}">
      <label for="module">Module</label><input id="module" name="module" value="{$this->escape($module)}">
      <button type="submit">Preview</button>
    </form>
    <pre>{$this->escape((string) json_encode($previewRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))}</pre>
  </section>
  <section class="card">
    <h2 style="margin-top:0">Platform Snapshot</h2>
    <pre>{$json}</pre>
  </section>
</main>
</body>
</html>
HTML;

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }

   /**
    * Handle preview response.
    *
    * @param Request $request
    * @return Response
    */
   private function previewResponse(Request $request): Response
   {
      $generator = $this->stringParam($request, 'generator');
      $name = $this->stringParam($request, 'name');
      if ($generator === null || $name === null) {
         return $this->json(400, [
            'error' => 'generator and name query params are required',
         ]);
      }

      $module = $this->stringParam($request, 'module') ?? 'Generated';
      $overwrite = $this->boolParam($request, 'overwrite');

      try {
         $rows = $this->generatorEngine->preview($generator, new GenerationRequest(
            basePath: $this->projectRoot,
            name: $name,
            module: $module,
            namespaceRoot: $this->namespaceRoot,
            overwrite: $overwrite,
         ));
      } catch (ToolingException $exception) {
         return $this->json(422, ['error' => $exception->getMessage()]);
      }

      return $this->json(200, [
         'generator' => $generator,
         'name' => $name,
         'module' => $module,
         'files' => array_map(static fn ($row): array => $row->toArray(), $rows),
      ]);
   }

   /**
    * @param array<string, mixed> $payload
    */
   private function json(int $status, array $payload): Response
   {
      return new Response(
         $status,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
      );
   }

   /**
    * Handle sub path.
    *
    * @param string $requestPath
    * @return string
    */
   private function subPath(string $requestPath): string
   {
      $prefix = rtrim($this->routePrefix, '/');
      $path = rtrim($requestPath, '/');

      if ($path === $prefix || $path === '') {
         return '/';
      }

      if (!str_starts_with($path, $prefix . '/')) {
         return '/';
      }

      $subPath = substr($path, strlen($prefix));
      return $subPath === '' ? '/' : $subPath;
   }

   /**
    * Handle string param.
    *
    * @param Request $request
    * @param string $name
    * @return ?string
    */
   private function stringParam(Request $request, string $name): ?string
   {
      $value = $request->getQueryParam($name);
      if (!is_string($value)) {
         return null;
      }

      $trimmed = trim($value);
      return $trimmed === '' ? null : $trimmed;
   }

   /**
    * Handle bool param.
    *
    * @param Request $request
    * @param string $name
    * @return bool
    */
   private function boolParam(Request $request, string $name): bool
   {
      $value = $request->getQueryParam($name);
      if (is_bool($value)) {
         return $value;
      }
      if (!is_string($value)) {
         return false;
      }

      $normalized = strtolower(trim($value));
      return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
   }

   /**
    * Handle escape.
    *
    * @param string $value
    * @return string
    */
   private function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}



