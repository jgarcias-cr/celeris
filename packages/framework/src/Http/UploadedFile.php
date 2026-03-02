<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use RuntimeException;

/**
 * Immutable representation of an uploaded file from an HTTP request.
 *
 * It stores client metadata and upload state and provides safe move/stream helpers for
 * request handlers and storage services.
 */
final class UploadedFile
{
   private bool $moved = false;

   /**
    * Create a new instance.
    *
    * @param ?string $tempPath
    * @param string $clientFilename
    * @param string $clientMediaType
    * @param int $size
    * @param int $error
    * @param ?string $streamContents
    * @return mixed
    */
   public function __construct(
      private ?string $tempPath,
      private string $clientFilename,
      private string $clientMediaType,
      private int $size,
      private int $error = UPLOAD_ERR_OK,
      private ?string $streamContents = null,
   ) {}

   /**
    * Get the client filename.
    *
    * @return string
    */
   public function getClientFilename(): string
   {
      return $this->clientFilename;
   }

   /**
    * Get the client media type.
    *
    * @return string
    */
   public function getClientMediaType(): string
   {
      return $this->clientMediaType;
   }

   /**
    * Get the size.
    *
    * @return int
    */
   public function getSize(): int
   {
      return $this->size;
   }

   /**
    * Get the error.
    *
    * @return int
    */
   public function getError(): int
   {
      return $this->error;
   }

   /**
    * Determine whether is valid.
    *
    * @return bool
    */
   public function isValid(): bool
   {
      return $this->error === UPLOAD_ERR_OK;
   }

   /**
    * Determine whether is moved.
    *
    * @return bool
    */
   public function isMoved(): bool
   {
      return $this->moved;
   }

   /**
    * Get the contents.
    *
    * @return string
    */
   public function getContents(): string
   {
      if ($this->streamContents !== null) {
         return $this->streamContents;
      }

      if ($this->tempPath === null || $this->tempPath === '') {
         return '';
      }

      $content = @file_get_contents($this->tempPath);
      if ($content === false) {
         throw new RuntimeException(sprintf('Unable to read uploaded file from "%s".', $this->tempPath));
      }

      return $content;
   }

   /**
    * Handle move to.
    *
    * @param string $targetPath
    * @return void
    */
   public function moveTo(string $targetPath): void
   {
      if ($this->moved) {
         throw new RuntimeException('Uploaded file has already been moved.');
      }

      if (!$this->isValid()) {
         throw new RuntimeException('Cannot move invalid uploaded file.');
      }

      if ($this->tempPath !== null && $this->tempPath !== '' && file_exists($this->tempPath)) {
         $moved = @move_uploaded_file($this->tempPath, $targetPath);
         if (!$moved) {
            $moved = @rename($this->tempPath, $targetPath);
         }
         if (!$moved) {
            $content = @file_get_contents($this->tempPath);
            if ($content === false || @file_put_contents($targetPath, $content) === false) {
               throw new RuntimeException(sprintf('Unable to move uploaded file to "%s".', $targetPath));
            }
            @unlink($this->tempPath);
         }
      } else {
         if (@file_put_contents($targetPath, $this->streamContents ?? '') === false) {
            throw new RuntimeException(sprintf('Unable to move uploaded file to "%s".', $targetPath));
         }
      }

      $this->moved = true;
   }

   /**
    * @param array{name:mixed,type:mixed,tmp_name:mixed,error:mixed,size:mixed} $file
    */
   public static function fromPhpSpec(array $file): self
   {
      return new self(
         isset($file['tmp_name']) ? (string) $file['tmp_name'] : null,
         (string) ($file['name'] ?? ''),
         (string) ($file['type'] ?? 'application/octet-stream'),
         (int) ($file['size'] ?? 0),
         (int) ($file['error'] ?? UPLOAD_ERR_OK),
      );
   }

   /**
    * Create an instance from psr uploaded file.
    *
    * @param object $file
    * @return self
    */
   public static function fromPsrUploadedFile(object $file): self
   {
      $clientFilename = method_exists($file, 'getClientFilename') ? (string) $file->getClientFilename() : '';
      $clientMediaType = method_exists($file, 'getClientMediaType') ? (string) $file->getClientMediaType() : 'application/octet-stream';
      $size = method_exists($file, 'getSize') ? (int) $file->getSize() : 0;
      $error = method_exists($file, 'getError') ? (int) $file->getError() : UPLOAD_ERR_OK;
      $streamContents = null;

      if (method_exists($file, 'getStream')) {
         $stream = $file->getStream();
         if (is_object($stream) && method_exists($stream, '__toString')) {
            $streamContents = (string) $stream;
         }
      }

      return new self(null, $clientFilename, $clientMediaType, $size, $error, $streamContents);
   }
}



