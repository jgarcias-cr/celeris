<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Purpose: carry trace context state across a single execution scope.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: passed through call chains to avoid hidden globals and retain deterministic state.
 */
final class TraceContext
{
   /**
    * Create a new instance.
    *
    * @param string $traceId
    * @param string $spanId
    * @param ?string $parentSpanId
    * @param bool $sampled
    * @param string $service
    * @return mixed
    */
   public function __construct(
      private string $traceId,
      private string $spanId,
      private ?string $parentSpanId,
      private bool $sampled,
      private string $service,
   ) {
      $this->traceId = self::normalizeTraceId($this->traceId);
      $this->spanId = self::normalizeSpanId($this->spanId);
      if ($this->parentSpanId !== null) {
         $this->parentSpanId = self::normalizeSpanId($this->parentSpanId);
      }
   }

   /**
    * Handle root.
    *
    * @param string $service
    * @param bool $sampled
    * @return self
    */
   public static function root(string $service, bool $sampled = true): self
   {
      return new self(self::randomHex(16), self::randomHex(8), null, $sampled, $service);
   }

   /**
    * Handle child.
    *
    * @param string $service
    * @return self
    */
   public function child(string $service): self
   {
      return new self($this->traceId, self::randomHex(8), $this->spanId, $this->sampled, $service);
   }

   /**
    * Handle trace id.
    *
    * @return string
    */
   public function traceId(): string
   {
      return $this->traceId;
   }

   /**
    * Handle span id.
    *
    * @return string
    */
   public function spanId(): string
   {
      return $this->spanId;
   }

   /**
    * Handle parent span id.
    *
    * @return ?string
    */
   public function parentSpanId(): ?string
   {
      return $this->parentSpanId;
   }

   /**
    * Handle sampled.
    *
    * @return bool
    */
   public function sampled(): bool
   {
      return $this->sampled;
   }

   /**
    * Handle service.
    *
    * @return string
    */
   public function service(): string
   {
      return $this->service;
   }

   /**
    * Return a copy with the service.
    *
    * @param string $service
    * @return self
    */
   public function withService(string $service): self
   {
      $copy = clone $this;
      $copy->service = $service;
      return $copy;
   }

   /**
    * Convert to trace parent.
    *
    * @return string
    */
   public function toTraceParent(): string
   {
      $flags = $this->sampled ? '01' : '00';
      return sprintf('00-%s-%s-%s', $this->traceId, $this->spanId, $flags);
   }

   /**
    * Handle normalize trace id.
    *
    * @param string $traceId
    * @return string
    */
   private static function normalizeTraceId(string $traceId): string
   {
      $clean = strtolower(trim($traceId));
      if (!preg_match('/^[a-f0-9]{32}$/', $clean)) {
         return self::randomHex(16);
      }

      return $clean;
   }

   /**
    * Handle normalize span id.
    *
    * @param string $spanId
    * @return string
    */
   private static function normalizeSpanId(string $spanId): string
   {
      $clean = strtolower(trim($spanId));
      if (!preg_match('/^[a-f0-9]{16}$/', $clean)) {
         return self::randomHex(8);
      }

      return $clean;
   }

   /**
    * Handle random hex.
    *
    * @param int $bytes
    * @return string
    */
   private static function randomHex(int $bytes): string
   {
      return bin2hex(random_bytes($bytes));
   }
}



