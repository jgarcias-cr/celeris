<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Encodes and decodes cookie values that carry session identifiers.
 */
interface CookieValueCodecInterface
{
   public function encode(string $value): string;

   public function decode(string $value): ?string;
}

