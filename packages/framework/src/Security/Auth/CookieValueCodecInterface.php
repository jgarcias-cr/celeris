<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Encodes and decodes cookie values that carry session identifiers.
 */
interface CookieValueCodecInterface
{
   /**
    * Encode a raw cookie value for storage.
    */
   public function encode(string $value): string;

   /**
    * Decode a stored cookie value back to its raw form when valid.
    */
   public function decode(string $value): ?string;
}

