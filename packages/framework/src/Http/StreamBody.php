<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use Closure;
use InvalidArgumentException;

/**
 * Purpose: implement stream body behavior for the Http subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by http components when stream body functionality is required.
 */
final class StreamBody implements ResponseBodyInterface
{
   /** @var Closure(callable(string): void): void */
   private Closure $emitter;
   private ?string $buffer = null;

   /**
    * @param callable(callable(string): void): void $emitter
    */
   public function __construct(callable $emitter)
   {
      $this->emitter = $emitter instanceof Closure ? $emitter : Closure::fromCallable($emitter);
   }

   /**
    * @param iterable<mixed> $chunks
    */
   public static function fromIterable(iterable $chunks): self
   {
      return new self(static function (callable $write) use ($chunks): void {
         foreach ($chunks as $chunk) {
            $write((string) $chunk);
         }
      });
   }

   /**
    * @param resource $resource
    */
   public static function fromResource(mixed $resource, int $chunkSize = 8192): self
   {
      if (!is_resource($resource)) {
         throw new InvalidArgumentException('StreamBody::fromResource expects a valid resource.');
      }

      return new self(static function (callable $write) use ($resource, $chunkSize): void {
         while (!feof($resource)) {
            $data = fread($resource, $chunkSize);
            if ($data === false || $data === '') {
               continue;
            }
            $write($data);
         }
      });
   }

   /**
    * Determine whether is streaming.
    *
    * @return bool
    */
   public function isStreaming(): bool
   {
      return true;
   }

   /**
    * Convert to string.
    *
    * @return string
    */
   public function toString(): string
   {
      if ($this->buffer !== null) {
         return $this->buffer;
      }

      $chunks = [];
      ($this->emitter)(static function (string $chunk) use (&$chunks): void {
         $chunks[] = $chunk;
      });
      $this->buffer = implode('', $chunks);

      return $this->buffer;
   }

   /**
    * Handle emit.
    *
    * @param callable $write
    * @return void
    */
   public function emit(callable $write): void
   {
      if ($this->buffer !== null) {
         $write($this->buffer);
         return;
      }

      $chunks = [];
      ($this->emitter)(static function (string $chunk) use (&$chunks, $write): void {
         $chunks[] = $chunk;
         $write($chunk);
      });
      $this->buffer = implode('', $chunks);
   }
}




