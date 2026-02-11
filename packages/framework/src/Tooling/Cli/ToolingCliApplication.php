<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Cli;

use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Generator\GenerationRequest;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\ToolingException;

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
            'list-generators' => $this->listGenerators($options),
            'graph' => $this->graph($options),
            'validate' => $this->validate($options),
            'generate' => $this->generate($positionals, $options),
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
      $this->out('  list-generators [--json]');
      $this->out('  graph [--format=text|json|dot]');
      $this->out('  validate [--json]');
      $this->out('  generate <generator> <name> [--module=Name] [--write] [--overwrite] [--json]');
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
         $this->err('Usage: generate <generator> <name> [--module=Name] [--write] [--overwrite] [--json]');
         return 1;
      }

      $module = is_string($options['module'] ?? null) ? (string) $options['module'] : 'Generated';
      $request = new GenerationRequest(
         basePath: $this->projectRoot,
         name: $name,
         module: $module,
         namespaceRoot: $this->namespaceRoot,
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



