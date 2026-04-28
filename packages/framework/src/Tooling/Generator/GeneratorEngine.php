<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

use Celeris\Framework\Tooling\Diff\UnifiedDiffBuilder;
use Celeris\Framework\Tooling\ToolingException;

/**
 * Orchestrate generator engine workflows within Tooling.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class GeneratorEngine
{
   /** @var array<string, GeneratorInterface> */
   private array $generators = [];

   /**
    * @param array<int, GeneratorInterface> $generators
    */
   public function __construct(
      private UnifiedDiffBuilder $diffBuilder = new UnifiedDiffBuilder(),
      array $generators = [],
   ) {
      foreach ($generators as $generator) {
         $this->register($generator);
      }

      if ($generators === []) {
         $this->register(new ModuleGenerator());
         $this->register(new ControllerGenerator());
      }
   }

   /**
    * Handle register.
    *
    * @param GeneratorInterface $generator
    * @return void
    */
   public function register(GeneratorInterface $generator): void
   {
      $this->generators[$generator->name()] = $generator;
   }

   /**
    * Handle list.
    * @return array<int, array{name:string, description:string}>
    */
   public function list(): array
   {
      $rows = [];
      foreach ($this->generators as $generator) {
         $rows[] = [
            'name' => $generator->name(),
            'description' => $generator->description(),
         ];
      }

      usort($rows, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));
      return $rows;
   }

   /**
    * Handle preview.
    * @return array<int, GeneratedFilePreview>
    */
   public function preview(string $generatorName, GenerationRequest $request): array
   {
      $generator = $this->generator($generatorName);
      $drafts = $generator->generate($request);
      $previews = [];

      foreach ($drafts as $draft) {
         $relativePath = $this->normalizeRelativePath($draft->path());
         $absolutePath = $request->basePath() . '/' . $relativePath;
         $exists = is_file($absolutePath);
         $before = $exists ? (string) file_get_contents($absolutePath) : '';
         $after = $draft->contents();

         $diff = $this->diffBuilder->build($before, $after, 'a/' . $relativePath, 'b/' . $relativePath);
         $previews[] = new GeneratedFilePreview($relativePath, $after, $diff, $exists);
      }

      usort($previews, static fn (GeneratedFilePreview $left, GeneratedFilePreview $right): int => strcmp($left->path(), $right->path()));
      return $previews;
   }

   /**
    * Handle apply.
    *
    * @param string $generatorName
    * @param GenerationRequest $request
    * @return GenerationApplyResult
    */
   public function apply(string $generatorName, GenerationRequest $request): GenerationApplyResult
   {
      $previews = $this->preview($generatorName, $request);
      $written = [];
      $skipped = [];

      foreach ($previews as $preview) {
         $absolutePath = $request->basePath() . '/' . $preview->path();
         $hasChanges = $preview->diff() !== '';

         if (!$hasChanges) {
            $skipped[] = $preview->path();
            continue;
         }

         if ($preview->exists() && !$request->overwrite()) {
            throw new ToolingException(sprintf(
               'Refusing to overwrite existing file "%s" without overwrite flag.',
               $preview->path()
            ));
         }

         $dir = dirname($absolutePath);
         if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ToolingException(sprintf('Could not create directory "%s".', $dir));
         }

         $bytes = file_put_contents($absolutePath, $preview->contents());
         if (!is_int($bytes) || $bytes < 0) {
            throw new ToolingException(sprintf('Could not write generated file "%s".', $preview->path()));
         }

         $written[] = $preview->path();
      }

      return new GenerationApplyResult($written, $skipped);
   }

   /**
    * Handle generator.
    *
    * @param string $generatorName
    * @return GeneratorInterface
    */
   private function generator(string $generatorName): GeneratorInterface
   {
      $generator = $this->generators[$generatorName] ?? null;
      if ($generator instanceof GeneratorInterface) {
         return $generator;
      }

      throw new ToolingException(sprintf('Unknown generator "%s".', $generatorName));
   }

   /**
    * Handle normalize relative path.
    *
    * @param string $path
    * @return string
    */
   private function normalizeRelativePath(string $path): string
   {
      $relative = ltrim(str_replace('\\', '/', $path), '/');
      if ($relative === '' || str_starts_with($relative, '../') || str_contains($relative, '/../')) {
         throw new ToolingException(sprintf('Invalid generator path "%s".', $path));
      }

      return $relative;
   }
}



