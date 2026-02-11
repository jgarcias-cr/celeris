<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Graph;

/**
 * Purpose: implement dependency graph behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when dependency graph functionality is required.
 */
final class DependencyGraph
{
   /** @var array<string, array{id:string,type:string}> */
   private array $nodes = [];

   /** @var array<string, DependencyEdge> */
   private array $edges = [];

   /**
    * Handle add node.
    *
    * @param string $id
    * @param string $type
    * @return void
    */
   public function addNode(string $id, string $type): void
   {
      $nodeId = trim($id);
      if ($nodeId === '') {
         return;
      }

      $resolvedType = trim($type) !== '' ? trim($type) : 'node';
      if (isset($this->nodes[$nodeId])) {
         if ($this->nodes[$nodeId]['type'] !== 'node' && $resolvedType === 'node') {
            return;
         }
      }

      $this->nodes[$nodeId] = ['id' => $nodeId, 'type' => $resolvedType];
   }

   /**
    * Handle add edge.
    *
    * @param string $from
    * @param string $to
    * @param string $type
    * @return void
    */
   public function addEdge(string $from, string $to, string $type = 'depends_on'): void
   {
      if (trim($from) === '' || trim($to) === '') {
         return;
      }

      $this->addNode($from, 'node');
      $this->addNode($to, 'node');

      $key = $from . '|' . $to . '|' . $type;
      $this->edges[$key] = new DependencyEdge($from, $to, $type);
   }

   /**
    * @return array<int, array{id:string,type:string}>
    */
   public function nodes(): array
   {
      $nodes = array_values($this->nodes);
      usort($nodes, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
      return $nodes;
   }

   /**
    * @return array<int, DependencyEdge>
    */
   public function edges(): array
   {
      $edges = array_values($this->edges);
      usort(
         $edges,
         static fn (DependencyEdge $left, DependencyEdge $right): int
            => strcmp($left->from() . '|' . $left->to() . '|' . $left->type(), $right->from() . '|' . $right->to() . '|' . $right->type())
      );
      return $edges;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'nodes' => $this->nodes(),
         'edges' => array_map(static fn (DependencyEdge $edge): array => $edge->toArray(), $this->edges()),
      ];
   }

   /**
    * Convert to dot.
    *
    * @param string $name
    * @return string
    */
   public function toDot(string $name = 'celeris_dependencies'): string
   {
      $lines = [sprintf('digraph %s {', $this->sanitizeId($name))];

      foreach ($this->nodes() as $node) {
         $label = addslashes($node['id'] . ' [' . $node['type'] . ']');
         $id = $this->sanitizeId($node['id']);
         $lines[] = sprintf('  %s [label="%s"];', $id, $label);
      }

      foreach ($this->edges() as $edge) {
         $from = $this->sanitizeId($edge->from());
         $to = $this->sanitizeId($edge->to());
         $type = addslashes($edge->type());
         $lines[] = sprintf('  %s -> %s [label="%s"];', $from, $to, $type);
      }

      $lines[] = '}';
      return implode("\n", $lines) . "\n";
   }

   /**
    * Handle sanitize id.
    *
    * @param string $id
    * @return string
    */
   private function sanitizeId(string $id): string
   {
      $sanitized = preg_replace('/[^A-Za-z0-9_]+/', '_', $id) ?? '';
      if ($sanitized === '') {
         return 'node';
      }

      if (preg_match('/^[0-9]/', $sanitized) === 1) {
         return 'n_' . $sanitized;
      }

      return $sanitized;
   }
}



