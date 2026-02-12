<?php

declare(strict_types=1);

$projectAutoload = __DIR__ . '/../vendor/autoload.php';
$workspaceAutoload = __DIR__ . '/../../../vendor/autoload.php';
$runtimeCanLoadComposer = PHP_VERSION_ID >= 80400;
if (
   !$runtimeCanLoadComposer
   || (!safeLoadAutoload($projectAutoload) && !safeLoadAutoload($workspaceAutoload))
) {
   require __DIR__ . '/../../framework/src/bootstrap.php';
}

use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\View\TemplateRendererFactory;

$basePath = dirname(__DIR__);
$runAll = in_array('--all', $argv, true);

$configLoader = new ConfigLoader(
   $basePath . '/config',
   new EnvironmentLoader(
      is_file($basePath . '/.env') ? $basePath . '/.env' : null,
      is_dir($basePath . '/secrets') ? $basePath . '/secrets' : null,
      false,
      true,
   ),
);

$snapshot = $configLoader->snapshot();
$items = $snapshot->getItems();
$appConfig = is_array($items['app'] ?? null) ? $items['app'] : [];
$viewConfig = is_array($appConfig['view'] ?? null) ? $appConfig['view'] : [];

$configuredEngine = strtolower(trim((string) ($viewConfig['engine'] ?? 'php')));
$engines = $runAll ? ['php', 'plates', 'twig', 'latte'] : [$configuredEngine];

$contacts = sampleContacts();
$contact = $contacts[0];

$failures = 0;

foreach ($engines as $engine) {
   $currentConfig = $viewConfig;
   $currentConfig['engine'] = $engine;
   ensureEngineDirectories($currentConfig, $basePath);

   try {
      $renderer = TemplateRendererFactory::fromConfig($currentConfig);
      $index = $renderer->render('contacts/index', [
         'title' => sprintf('Contacts [%s]', $engine),
         'contacts' => $contacts,
      ]);
      $show = $renderer->render('contacts/show', [
         'title' => sprintf('Contact [%s]', $engine),
         'contact' => $contact,
      ]);

      printf(
         "[ok] engine=%s index_bytes=%d show_bytes=%d\n",
         $engine,
         strlen($index),
         strlen($show),
      );
   } catch (Throwable $exception) {
      $failures++;
      printf("[fail] engine=%s reason=%s\n", $engine, $exception->getMessage());
   }
}

if ($runAll) {
   echo "Tip: set VIEW_ENGINE in .env to one engine and run this script without --all.\n";
}

exit($failures === 0 ? 0 : 1);

/**
 * @return array<int, object>
 */
function sampleContacts(): array
{
   return [
      (object) [
         'id' => 1,
         'firstName' => 'Ada',
         'lastName' => 'Lovelace',
         'phone' => '+1-555-0100',
         'address' => '10 Computing Ln',
         'age' => 36,
      ],
      (object) [
         'id' => 2,
         'firstName' => 'Grace',
         'lastName' => 'Hopper',
         'phone' => '+1-555-0101',
         'address' => '12 Compiler St',
         'age' => 44,
      ],
   ];
}

/**
 * @param array<string, mixed> $config
 */
function ensureEngineDirectories(array $config, string $basePath): void
{
   $engine = strtolower(trim((string) ($config['engine'] ?? 'php')));
   if ($engine === 'twig') {
      $twigConfig = $config['twig'] ?? null;
      if (is_array($twigConfig)) {
         $cache = $twigConfig['cache'] ?? null;
         if (is_string($cache) && trim($cache) !== '') {
            ensureDirectory(resolvePath($cache, $basePath));
         }
      }
   }

   if ($engine === 'latte') {
      $latteConfig = $config['latte'] ?? null;
      if (is_array($latteConfig)) {
         $tempPath = $latteConfig['temp_path'] ?? null;
         if (is_string($tempPath) && trim($tempPath) !== '') {
            ensureDirectory(resolvePath($tempPath, $basePath));
         }
      }
   }
}

function ensureDirectory(string $path): void
{
   if (is_dir($path)) {
      return;
   }

   @mkdir($path, 0775, true);
}

function resolvePath(string $path, string $basePath): string
{
   if ($path === '') {
      return $basePath;
   }

   if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
      return $path;
   }

   return rtrim($basePath, '/\\') . '/' . ltrim($path, '/\\');
}

function safeLoadAutoload(string $path): bool
{
   if (!is_file($path)) {
      return false;
   }

   try {
      require $path;
      return true;
   } catch (\Throwable $exception) {
      fwrite(STDERR, sprintf("[warn] Autoload skipped (%s): %s\n", $path, $exception->getMessage()));
      return false;
   }
}
