<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Diff;

/**
 * Compose unified diff builder output from incremental inputs.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class UnifiedDiffBuilder
{
   /**
    * Handle build.
    *
    * @param string $old
    * @param string $new
    * @param string $oldLabel
    * @param string $newLabel
    * @return string
    */
   public function build(string $old, string $new, string $oldLabel = 'a/file', string $newLabel = 'b/file'): string
   {
      if ($old === $new) {
         return '';
      }

      $oldLines = $this->splitLines($old);
      $newLines = $this->splitLines($new);
      $operations = $this->operations($oldLines, $newLines);

      $oldCount = count($oldLines);
      $newCount = count($newLines);
      $oldStart = $oldCount === 0 ? 0 : 1;
      $newStart = $newCount === 0 ? 0 : 1;

      $lines = [
         sprintf('--- %s', $oldLabel),
         sprintf('+++ %s', $newLabel),
         sprintf('@@ -%d,%d +%d,%d @@', $oldStart, $oldCount, $newStart, $newCount),
      ];

      foreach ($operations as $operation) {
         $lines[] = $operation['prefix'] . $operation['line'];
      }

      return implode("\n", $lines) . "\n";
   }

   /**
    * @return array<int, string>
    */
   private function splitLines(string $content): array
   {
      if ($content === '') {
         return [];
      }

      $normalized = str_replace(["\r\n", "\r"], "\n", $content);
      return explode("\n", $normalized);
   }

   /**
    * @param array<int, string> $oldLines
    * @param array<int, string> $newLines
    * @return array<int, array{prefix:string, line:string}>
    */
   private function operations(array $oldLines, array $newLines): array
   {
      $matrix = $this->lcsMatrix($oldLines, $newLines);
      $ops = [];
      $i = 0;
      $j = 0;
      $oldCount = count($oldLines);
      $newCount = count($newLines);

      while ($i < $oldCount && $j < $newCount) {
         if ($oldLines[$i] === $newLines[$j]) {
            $ops[] = ['prefix' => ' ', 'line' => $oldLines[$i]];
            $i++;
            $j++;
            continue;
         }

         if ($matrix[$i + 1][$j] >= $matrix[$i][$j + 1]) {
            $ops[] = ['prefix' => '-', 'line' => $oldLines[$i]];
            $i++;
            continue;
         }

         $ops[] = ['prefix' => '+', 'line' => $newLines[$j]];
         $j++;
      }

      while ($i < $oldCount) {
         $ops[] = ['prefix' => '-', 'line' => $oldLines[$i]];
         $i++;
      }

      while ($j < $newCount) {
         $ops[] = ['prefix' => '+', 'line' => $newLines[$j]];
         $j++;
      }

      return $ops;
   }

   /**
    * @param array<int, string> $oldLines
    * @param array<int, string> $newLines
    * @return array<int, array<int, int>>
    */
   private function lcsMatrix(array $oldLines, array $newLines): array
   {
      $n = count($oldLines);
      $m = count($newLines);
      $matrix = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

      for ($i = $n - 1; $i >= 0; $i--) {
         for ($j = $m - 1; $j >= 0; $j--) {
            if ($oldLines[$i] === $newLines[$j]) {
               $matrix[$i][$j] = $matrix[$i + 1][$j + 1] + 1;
            } else {
               $matrix[$i][$j] = max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
            }
         }
      }

      return $matrix;
   }
}



