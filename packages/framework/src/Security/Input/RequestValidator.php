<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Input;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Security\SecurityException;

/**
 * Purpose: implement request validator behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when request validator functionality is required.
 */
final class RequestValidator
{
   /**
    * Create a new instance.
    *
    * @param int $maxBodyBytes
    * @param int $maxHeaderValueLength
    * @return mixed
    */
   public function __construct(
      private int $maxBodyBytes = 1048576,
      private int $maxHeaderValueLength = 8192,
   ) {
   }

   /**
    * Handle validate.
    *
    * @param Request $request
    * @return void
    */
   public function validate(Request $request): void
   {
      if (!preg_match('/^[A-Z]+$/', $request->getMethod())) {
         throw new SecurityException('Invalid HTTP method.', 400);
      }

      $path = $request->getPath();
      if (!str_starts_with($path, '/')) {
         throw new SecurityException('Invalid request path.', 400);
      }
      if (str_contains($path, "\0")) {
         throw new SecurityException('Invalid null byte in request path.', 400);
      }

      $contentLength = $request->getHeader('content-length');
      if ($contentLength !== null && $contentLength !== '') {
         if (!ctype_digit($contentLength)) {
            throw new SecurityException('Invalid content-length header.', 400);
         }
         if ((int) $contentLength > $this->maxBodyBytes) {
            throw new SecurityException('Request body is too large.', 413);
         }
      }

      foreach ($request->headers()->toMultiValueArray() as $name => $values) {
         foreach ($values as $value) {
            if (strlen($value) > $this->maxHeaderValueLength) {
               throw new SecurityException(sprintf('Header "%s" exceeds max length.', $name), 400);
            }
         }
      }
   }
}



