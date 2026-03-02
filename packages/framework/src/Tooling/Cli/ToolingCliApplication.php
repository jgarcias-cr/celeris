<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Cli;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\Routing\ProjectRouteInspector;
use Celeris\Framework\Tooling\Security\AppKeyManager;
use Celeris\Framework\Tooling\ToolingException;
use Celeris\Framework\Tooling\Web\DeveloperUiController;

/**
 * Purpose: implement tooling cli application behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when tooling cli application functionality is required.
 */
final class ToolingCliApplication
{
   /**
    * Create a new instance.
    *
    * @param GeneratorEngine $generatorEngine
    * @param DependencyGraphBuilder $dependencyGraphBuilder
    * @param ArchitectureDecisionValidator $architectureValidator
    * @param string $projectRoot
    * @param string $namespaceRoot
    * @return mixed
    */
   public function __construct(
      private GeneratorEngine $generatorEngine,
      private DependencyGraphBuilder $dependencyGraphBuilder,
      private ArchitectureDecisionValidator $architectureValidator,
      private string $projectRoot,
      private string $namespaceRoot = 'App',
   ) {
   }

   /**
    * @param array<int, string> $argv
    */
   public function run(array $argv): int
   {
      $command = $argv[1] ?? 'help';
      [$positionals, $options] = $this->parseArgs(array_slice($argv, 2));

      try {
         return match ($command) {
            'help', '--help', '-h' => $this->help(),
            'app-key' => $this->appKey($options),
            'routes:list' => $this->routesList($options),
            'list-generators' => $this->listGenerators($options),
            'graph' => $this->graph($options),
            'validate' => $this->validate($options),
            'generate' => $this->generate($positionals, $options),
            'schema:connections' => $this->schemaConnections($options),
            'schema:tables' => $this->schemaTables($options),
            'schema:describe' => $this->schemaDescribe($positionals, $options),
            'scaffold:preview' => $this->scaffoldPreview($positionals, $options),
            'scaffold:apply' => $this->scaffoldApply($positionals, $options),
            'compat:check' => $this->compatCheck($positionals, $options),
            'compat:baseline:save' => $this->compatBaselineSave($positionals, $options),
            default => $this->unknown($command),
         };
      } catch (ToolingException $exception) {
         $this->err('Error: ' . $exception->getMessage());
         return 1;
      }
   }

   /**
    * Handle help.
    *
    * @return int
    */
   private function help(): int
   {
      $this->out('Celeris Tooling CLI');
      $this->out('');
      $this->out('Commands:');
      $this->out('  app-key [--force] [--env=.env] [--show] [--json]');
      $this->out('  routes:list [--json]');
      $this->out('  list-generators [--json]');
      $this->out('  graph [--format=text|json|dot]');
      $this->out('  validate [--json]');
      $this->out('  generate <generator> <name> [--module=Name] [--routing-type=attribute|php] [--write] [--overwrite] [--json]');
      $this->out('  schema:connections [--json]');
      $this->out('  schema:tables [--connection=name] [--json]');
      $this->out('  schema:describe <table> [--connection=name] [--json]');
      $this->out('  scaffold:preview <table> [--connection=name] [--artifacts=a,b,c] [--routing-type=attribute|php] [--json]');
      $this->out('  scaffold:apply <table> [--connection=name] [--artifacts=a,b,c] [--routing-type=attribute|php] [--json]');
      $this->out('  compat:check <table> [--connection=name] [--json]');
      $this->out('  compat:baseline:save <table> [--connection=name] [--json]');
      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function listGenerators(array $options): int
   {
      $rows = $this->generatorEngine->list();
      if ($this->isJson($options)) {
         $this->out((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      foreach ($rows as $row) {
         $this->out(sprintf('%s: %s', $row['name'], $row['description']));
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function appKey(array $options): int
   {
      $manager = new AppKeyManager();
      $key = $manager->generate();
      $force = $this->asBool($options['force'] ?? false);
      $show = $this->asBool($options['show'] ?? false);
      $envFile = is_string($options['env'] ?? null) ? trim((string) $options['env']) : '.env';
      if ($envFile === '') {
         $envFile = '.env';
      }

      $result = $manager->write($this->projectRoot, $key, $envFile, $force);
      $payload = [
         'env_file' => $result['env_file'],
         'created_env' => $result['created_env'],
         'updated' => $result['updated'],
         'existing_key' => $result['existing_key'],
         'key' => $show ? $key : '[hidden]',
      ];

      if ($this->isJson($options)) {
         $this->out((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      if ($result['updated']) {
         $this->out(sprintf('APP_KEY set in %s', $result['env_file']));
      } else {
         $this->out(sprintf('APP_KEY already set in %s (use --force to rotate)', $result['env_file']));
      }

      if ($show) {
         $this->out('APP_KEY=' . $key);
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function routesList(array $options): int
   {
      $inspector = new ProjectRouteInspector();
      $rows = $inspector->inspect($this->projectRoot);

      if ($this->isJson($options)) {
         $this->out((string) json_encode(['items' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      if ($rows === []) {
         $this->out('No routes found.');
         return 0;
      }

      $methodWidth = max(6, ...array_map(static fn (array $row): int => strlen($row['method']), $rows));
      $uriWidth = max(3, ...array_map(static fn (array $row): int => strlen($row['uri']), $rows));
      $actionWidth = max(6, ...array_map(static fn (array $row): int => strlen($row['action']), $rows));

      $this->out(sprintf(
         "%-{$methodWidth}s  %-{$uriWidth}s  %-{$actionWidth}s  %s",
         'METHOD',
         'URI',
         'ACTION',
         'MIDDLEWARE',
      ));
      $this->out(str_repeat('-', $methodWidth + $uriWidth + $actionWidth + 16));

      foreach ($rows as $row) {
         $middleware = $row['middleware'] === [] ? '-' : implode(', ', $row['middleware']);
         $this->out(sprintf(
            "%-{$methodWidth}s  %-{$uriWidth}s  %-{$actionWidth}s  %s",
            $row['method'],
            $row['uri'],
            $row['action'],
            $middleware,
         ));
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function graph(array $options): int
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      $format = strtolower((string) ($options['format'] ?? 'text'));

      if ($format === 'json') {
         $this->out((string) json_encode($graph->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      if ($format === 'dot') {
         $this->out($graph->toDot());
         return 0;
      }

      $map = $this->dependencyGraphBuilder->moduleDependencyMap();
      foreach ($map as $module => $deps) {
         $this->out(sprintf('%s -> %s', $module, $deps === [] ? '(none)' : implode(', ', $deps)));
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function validate(array $options): int
   {
      $graph = $this->dependencyGraphBuilder->buildModuleGraph();
      $report = $this->architectureValidator->validate($graph);

      if ($this->isJson($options)) {
         $this->out((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      } else {
         $this->out($report->isValid() ? 'VALID' : 'INVALID');
         foreach ($report->violations() as $violation) {
            $this->out(sprintf('[%s] %s: %s', strtoupper($violation->severity()), $violation->rule(), $violation->message()));
         }
      }

      return $report->isValid() ? 0 : 1;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function generate(array $positionals, array $options): int
   {
      $generator = $positionals[0] ?? null;
      $name = $positionals[1] ?? null;
      if (!is_string($generator) || trim($generator) === '' || !is_string($name) || trim($name) === '') {
         $this->err('Usage: generate <generator> <name> [--module=Name] [--routing-type=attribute|php] [--write] [--overwrite] [--json]');
         return 1;
      }

      $module = is_string($options['module'] ?? null) ? (string) $options['module'] : 'Generated';
      $routingType = strtolower((string) ($options['routing-type'] ?? 'attribute'));
      $routingType = $routingType === 'php' ? 'php' : 'attribute';
      $request = new GenerationRequest(
         basePath: $this->projectRoot,
         name: $name,
         module: $module,
         namespaceRoot: $this->namespaceRoot,
         options: ['routing_type' => $routingType],
         overwrite: $this->asBool($options['overwrite'] ?? false),
      );

      $previews = $this->generatorEngine->preview($generator, $request);

      if ($this->asBool($options['write'] ?? false)) {
         $result = $this->generatorEngine->apply($generator, $request);
         $payload = [
            'written' => $result->written(),
            'skipped' => $result->skipped(),
         ];

         if ($this->isJson($options)) {
            $this->out((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         } else {
            foreach ($result->written() as $path) {
               $this->out('WROTE ' . $path);
            }
            foreach ($result->skipped() as $path) {
               $this->out('SKIP ' . $path);
            }
         }

         return 0;
      }

      if ($this->isJson($options)) {
         $this->out((string) json_encode([
            'generator' => $generator,
            'name' => $name,
            'module' => $module,
            'routing_type' => $routingType,
            'preview' => array_map(static fn ($preview): array => $preview->toArray(), $previews),
         ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      foreach ($previews as $preview) {
         $this->out('# ' . $preview->path());
         $this->out($preview->diff() === '' ? '(no changes)' : $preview->diff());
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function schemaConnections(array $options): int
   {
      $data = $this->toolingApi('GET', '/schema/connections');
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $items = is_array($data['items'] ?? null) ? $data['items'] : [];
      if ($items === []) {
         $this->out('No connections found.');
         return 0;
      }

      foreach ($items as $row) {
         if (!is_array($row)) {
            continue;
         }
         $name = (string) ($row['name'] ?? '');
         $driver = (string) ($row['driver'] ?? 'unknown');
         $default = (bool) ($row['default'] ?? false);
         $this->out(sprintf('%s [%s]%s', $name, $driver, $default ? ' (default)' : ''));
      }

      return 0;
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function schemaTables(array $options): int
   {
      $query = [];
      if (is_string($options['connection'] ?? null) && trim((string) $options['connection']) !== '') {
         $query['connection'] = trim((string) $options['connection']);
      }

      $data = $this->toolingApi('GET', '/schema/tables', $query);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $items = is_array($data['items'] ?? null) ? $data['items'] : [];
      if ($items === []) {
         $this->out('No tables found.');
         return 0;
      }

      foreach ($items as $name) {
         $this->out((string) $name);
      }

      return 0;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function schemaDescribe(array $positionals, array $options): int
   {
      $table = trim((string) ($positionals[0] ?? ''));
      if ($table === '') {
         $this->err('Usage: schema:describe <table> [--connection=name] [--json]');
         return 1;
      }

      $query = [];
      if (is_string($options['connection'] ?? null) && trim((string) $options['connection']) !== '') {
         $query['connection'] = trim((string) $options['connection']);
      }

      $data = $this->toolingApi('GET', '/schema/tables/' . rawurlencode($table), $query);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $schema = is_array($data['schema'] ?? null) ? $data['schema'] : [];
      $columns = is_array($schema['columns'] ?? null) ? $schema['columns'] : [];
      $this->out('Table: ' . (string) ($data['table'] ?? $table));
      $this->out('Columns: ' . count($columns));

      foreach ($columns as $row) {
         if (!is_array($row)) {
            continue;
         }
         $this->out(sprintf(
            '- %s (%s) nullable=%s default=%s',
            (string) ($row['name'] ?? ''),
            (string) ($row['type'] ?? ''),
            ((bool) ($row['nullable'] ?? false)) ? 'yes' : 'no',
            array_key_exists('default', $row) && $row['default'] !== null ? (string) $row['default'] : '-'
         ));
      }

      return 0;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function scaffoldPreview(array $positionals, array $options): int
   {
      $table = trim((string) ($positionals[0] ?? ''));
      if ($table === '') {
         $this->err('Usage: scaffold:preview <table> [--connection=name] [--artifacts=a,b,c] [--routing-type=attribute|php] [--json]');
         return 1;
      }

      $payload = $this->scaffoldPayload($table, $options);
      $data = $this->toolingApi('POST', '/scaffold/preview', [], $payload);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $files = is_array($data['files'] ?? null) ? $data['files'] : [];
      $this->out(sprintf('Preview files: %d', count($files)));
      foreach ($files as $row) {
         if (!is_array($row)) {
            continue;
         }
         $diff = (string) ($row['diff'] ?? '');
         $this->out(sprintf(
            '- %s | exists=%s | changed=%s',
            (string) ($row['path'] ?? ''),
            ((bool) ($row['exists'] ?? false)) ? 'yes' : 'no',
            $diff !== '' ? 'yes' : 'no'
         ));
      }

      return 0;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function scaffoldApply(array $positionals, array $options): int
   {
      $table = trim((string) ($positionals[0] ?? ''));
      if ($table === '') {
         $this->err('Usage: scaffold:apply <table> [--connection=name] [--artifacts=a,b,c] [--routing-type=attribute|php] [--json]');
         return 1;
      }

      $payload = $this->scaffoldPayload($table, $options);
      $data = $this->toolingApi('POST', '/scaffold/apply', [], $payload);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $written = is_array($data['written'] ?? null) ? $data['written'] : [];
      $skipped = is_array($data['skipped'] ?? null) ? $data['skipped'] : [];
      foreach ($written as $path) {
         $this->out('WROTE ' . (string) $path);
      }
      foreach ($skipped as $path) {
         $this->out('SKIP ' . (string) $path);
      }
      $this->out(sprintf('Done: written=%d skipped=%d', count($written), count($skipped)));

      return 0;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function compatCheck(array $positionals, array $options): int
   {
      $table = trim((string) ($positionals[0] ?? ''));
      if ($table === '') {
         $this->err('Usage: compat:check <table> [--connection=name] [--json]');
         return 1;
      }

      $query = ['table' => $table];
      if (is_string($options['connection'] ?? null) && trim((string) $options['connection']) !== '') {
         $query['connection'] = trim((string) $options['connection']);
      }

      $data = $this->toolingApi('GET', '/compat/breaking-changes', $query);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $changes = is_array($data['breaking_changes'] ?? null) ? $data['breaking_changes'] : [];
      $this->out(sprintf('Breaking changes: %d', count($changes)));
      foreach ($changes as $change) {
         if (!is_array($change)) {
            continue;
         }
         $this->out(sprintf(
            '- %s: %s',
            (string) ($change['type'] ?? 'unknown'),
            (string) ($change['message'] ?? json_encode($change, JSON_UNESCAPED_SLASHES))
         ));
      }

      return count($changes) === 0 ? 0 : 1;
   }

   /**
    * @param array<int, string> $positionals
    * @param array<string, string|bool> $options
    */
   private function compatBaselineSave(array $positionals, array $options): int
   {
      $table = trim((string) ($positionals[0] ?? ''));
      if ($table === '') {
         $this->err('Usage: compat:baseline:save <table> [--connection=name] [--json]');
         return 1;
      }

      $payload = ['table' => $table];
      if (is_string($options['connection'] ?? null) && trim((string) $options['connection']) !== '') {
         $payload['connection'] = trim((string) $options['connection']);
      }

      $data = $this->toolingApi('POST', '/compat/baseline/save', [], $payload);
      if ($this->isJson($options)) {
         $this->out((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
         return 0;
      }

      $this->out('Baseline saved for table ' . $table . '.');
      if (is_string($data['path'] ?? null) && $data['path'] !== '') {
         $this->out('Path: ' . $data['path']);
      }

      return 0;
   }

   /**
    * Handle unknown.
    *
    * @param string $command
    * @return int
    */
   private function unknown(string $command): int
   {
      $this->err(sprintf('Unknown command "%s".', $command));
      $this->help();
      return 1;
   }

   /**
    * @param array<string, string|bool> $options
    * @return array<string, mixed>
    */
   private function scaffoldPayload(string $table, array $options): array
   {
      $payload = [
         'table' => $table,
         'routing_type' => strtolower((string) ($options['routing-type'] ?? 'attribute')) === 'php' ? 'php' : 'attribute',
         'artifacts' => $this->defaultScaffoldArtifacts(),
      ];

      if (is_string($options['connection'] ?? null) && trim((string) $options['connection']) !== '') {
         $payload['connection'] = trim((string) $options['connection']);
      }

      if (is_string($options['artifacts'] ?? null) && trim((string) $options['artifacts']) !== '') {
         $payload['artifacts'] = $this->csvList((string) $options['artifacts']);
      }

      return $payload;
   }

   /**
    * @return array<int, string>
    */
   private function defaultScaffoldArtifacts(): array
   {
      return [
         'model',
         'repository',
         'service',
         'controller',
         'dto.request',
         'dto.response',
      ];
   }

   /**
    * @return array<int, string>
    */
   private function csvList(string $value): array
   {
      $items = array_map(static fn (string $item): string => trim($item), explode(',', $value));
      $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
      return array_values(array_unique($items));
   }

   /**
    * @param string $method
    * @param string $path
    * @param array<string, mixed> $query
    * @param array<string, mixed>|null $payload
    * @return array<string, mixed>
    */
   private function toolingApi(string $method, string $path, array $query = [], ?array $payload = null): array
   {
      $controller = new DeveloperUiController(
         $this->generatorEngine,
         $this->dependencyGraphBuilder,
         $this->architectureValidator,
         $this->projectRoot,
         '/__dev/tooling',
         $this->namespaceRoot,
      );

      $previousEnv = $_ENV['APP_ENV'] ?? null;
      $previousServerEnv = $_SERVER['APP_ENV'] ?? null;
      $previousToolingEnabled = $_ENV['TOOLING_ENABLED'] ?? null;
      $previousServerToolingEnabled = $_SERVER['TOOLING_ENABLED'] ?? null;
      $_ENV['APP_ENV'] = (string) ($previousEnv ?? 'development');
      $_SERVER['APP_ENV'] = (string) ($previousServerEnv ?? $_ENV['APP_ENV']);
      $_ENV['TOOLING_ENABLED'] = 'true';
      $_SERVER['TOOLING_ENABLED'] = 'true';

      try {
         $json = '';
         $headers = [];
         if ($payload !== null) {
            $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
            $headers['content-type'] = 'application/json';
         }

         $request = new Request(
            strtoupper($method),
            '/__dev/tooling/api/v1' . $path,
            $headers,
            $query,
            $json,
         );
         $ctx = new RequestContext('tooling-cli-' . uniqid('', true), microtime(true), ['REMOTE_ADDR' => '127.0.0.1']);
         $response = $controller->handle($ctx, $request);

         $decoded = json_decode($response->getBody(), true);
         if (!is_array($decoded)) {
            throw new ToolingException(sprintf(
               'Tooling API returned non-JSON response (status %d).',
               $response->getStatus()
            ));
         }

         if (($decoded['status'] ?? null) !== 'ok') {
            $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
            $first = is_array($errors[0] ?? null) ? $errors[0] : [];
            $message = is_string($first['message'] ?? null) ? $first['message'] : 'Unknown tooling API error.';
            throw new ToolingException($message);
         }

         $data = $decoded['data'] ?? [];
         return is_array($data) ? $data : [];
      } finally {
         if ($previousEnv === null) {
            unset($_ENV['APP_ENV']);
         } else {
            $_ENV['APP_ENV'] = $previousEnv;
         }
         if ($previousServerEnv === null) {
            unset($_SERVER['APP_ENV']);
         } else {
            $_SERVER['APP_ENV'] = $previousServerEnv;
         }
         if ($previousToolingEnabled === null) {
            unset($_ENV['TOOLING_ENABLED']);
         } else {
            $_ENV['TOOLING_ENABLED'] = $previousToolingEnabled;
         }
         if ($previousServerToolingEnabled === null) {
            unset($_SERVER['TOOLING_ENABLED']);
         } else {
            $_SERVER['TOOLING_ENABLED'] = $previousServerToolingEnabled;
         }
      }
   }

   /**
    * @param array<int, string> $args
    * @return array{0:array<int, string>, 1:array<string, string|bool>}
    */
   private function parseArgs(array $args): array
   {
      $positionals = [];
      $options = [];

      foreach ($args as $arg) {
         if (!str_starts_with($arg, '--')) {
            $positionals[] = $arg;
            continue;
         }

         $payload = substr($arg, 2);
         if (str_contains($payload, '=')) {
            [$key, $value] = explode('=', $payload, 2);
            $options[$key] = $value;
            continue;
         }

         $options[$payload] = true;
      }

      return [$positionals, $options];
   }

   /**
    * @param array<string, string|bool> $options
    */
   private function isJson(array $options): bool
   {
      return $this->asBool($options['json'] ?? false);
   }

   /**
    * Handle as bool.
    *
    * @param string|bool $value
    * @return bool
    */
   private function asBool(string|bool $value): bool
   {
      if (is_bool($value)) {
         return $value;
      }

      return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
   }

   /**
    * Handle out.
    *
    * @param string $line
    * @return void
    */
   private function out(string $line): void
   {
      fwrite(STDOUT, $line . PHP_EOL);
   }

   /**
    * Handle err.
    *
    * @param string $line
    * @return void
    */
   private function err(string $line): void
   {
      fwrite(STDERR, $line . PHP_EOL);
   }
}
