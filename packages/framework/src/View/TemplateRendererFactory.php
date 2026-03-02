<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Implement template renderer factory behavior for the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class TemplateRendererFactory
{
   /**
    * @param array<string, mixed> $config
    */
   public static function fromConfig(
      array $config,
      ?object $twigEnvironment = null,
      ?object $platesEngine = null,
      ?object $latteEngine = null,
   ): TemplateRendererInterface {
      $engine = self::normalizeEngine((string) ($config['engine'] ?? 'php'));
      $viewsPath = self::resolveViewsPath($config);

      return match ($engine) {
         'php' => new PhpTemplateRenderer($viewsPath, self::resolveExtension($config, 'php', 'php')),
         'twig' => new TwigTemplateRenderer(
            $twigEnvironment ?? self::buildTwigEnvironment($viewsPath, $config),
            self::resolveExtension($config, 'twig', 'twig'),
         ),
         'plates' => new PlatesTemplateRenderer(
            $platesEngine ?? self::buildPlatesEngine($viewsPath, $config),
            self::resolveExtension($config, 'plates', 'php'),
         ),
         'latte' => new LatteTemplateRenderer(
            $latteEngine ?? self::buildLatteEngine($config),
            $viewsPath,
            self::resolveExtension($config, 'latte', 'latte'),
         ),
         default => throw ViewException::unsupportedEngine($engine),
      };
   }

   /**
    * @param array<string, mixed> $config
    * @return object
    */
   private static function buildTwigEnvironment(string $viewsPath, array $config): object
   {
      if (!class_exists('Twig\\Environment') || !class_exists('Twig\\Loader\\FilesystemLoader')) {
         throw ViewException::missingDependency('twig', 'twig/twig', 'Twig\\Environment');
      }

      $twigConfig = $config['twig'] ?? [];
      $options = is_array($twigConfig) ? $twigConfig : [];

      /** @var class-string $loaderClass */
      $loaderClass = 'Twig\\Loader\\FilesystemLoader';
      /** @var class-string $environmentClass */
      $environmentClass = 'Twig\\Environment';

      $loader = new $loaderClass($viewsPath);
      return new $environmentClass($loader, $options);
   }

   /**
    * @param array<string, mixed> $config
    * @return object
    */
   private static function buildPlatesEngine(string $viewsPath, array $config): object
   {
      if (!class_exists('League\\Plates\\Engine')) {
         throw ViewException::missingDependency('plates', 'league/plates', 'League\\Plates\\Engine');
      }

      /** @var class-string $engineClass */
      $engineClass = 'League\\Plates\\Engine';
      $engine = new $engineClass($viewsPath);

      if (method_exists($engine, 'setFileExtension')) {
         $engine->setFileExtension(self::resolveExtension($config, 'plates', 'php'));
      }

      return $engine;
   }

   /**
    * @param array<string, mixed> $config
    * @return object
    */
   private static function buildLatteEngine(array $config): object
   {
      if (!class_exists('Latte\\Engine')) {
         throw ViewException::missingDependency('latte', 'latte/latte', 'Latte\\Engine');
      }

      /** @var class-string $engineClass */
      $engineClass = 'Latte\\Engine';
      $engine = new $engineClass();

      $latteConfig = $config['latte'] ?? null;
      if (is_array($latteConfig) && method_exists($engine, 'setTempDirectory')) {
         $tempPath = $latteConfig['temp_path'] ?? null;
         if (is_string($tempPath) && trim($tempPath) !== '') {
            $engine->setTempDirectory($tempPath);
         }
      }

      return $engine;
   }

   /**
    * @param array<string, mixed> $config
    */
   private static function resolveViewsPath(array $config): string
   {
      $configured = $config['views_path'] ?? null;
      if (is_string($configured) && trim($configured) !== '') {
         return rtrim(trim($configured), '/\\');
      }

      $workingDirectory = getcwd();
      if (is_string($workingDirectory) && $workingDirectory !== '') {
         return rtrim($workingDirectory, '/\\') . '/views';
      }

      return 'views';
   }

   /**
    * @param array<string, mixed> $config
    */
   private static function resolveExtension(array $config, string $engine, string $default): string
   {
      $extensions = $config['extensions'] ?? null;
      if (!is_array($extensions)) {
         return $default;
      }

      $value = $extensions[$engine] ?? null;
      if (!is_string($value) || trim($value) === '') {
         return $default;
      }

      return trim($value);
   }

   private static function normalizeEngine(string $engine): string
   {
      return strtolower(trim($engine));
   }
}

