<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use DateTimeInterface;

/**
 * Value object representing one outgoing `Set-Cookie` header.
 *
 * It captures cookie attributes (path, domain, flags, expiration, same-site) and can
 * render the final header string when attached to a response.
 */
final class SetCookie
{
   /**
    * Create a new instance.
    *
    * @param string $name
    * @param string $value
    * @param ?DateTimeInterface $expires
    * @param string $path
    * @param ?string $domain
    * @param bool $secure
    * @param bool $httpOnly
    * @param ?string $sameSite
    * @return mixed
    */
   public function __construct(
      private string $name,
      private string $value,
      private ?DateTimeInterface $expires = null,
      private string $path = '/',
      private ?string $domain = null,
      private bool $secure = false,
      private bool $httpOnly = true,
      private ?string $sameSite = 'Lax',
   ) {}

   /**
    * Get the name.
    *
    * @return string
    */
   public function getName(): string
   {
      return $this->name;
   }

   /**
    * Get the value.
    *
    * @return string
    */
   public function getValue(): string
   {
      return $this->value;
   }

   /**
    * Return a copy with the value.
    *
    * @param string $value
    * @return self
    */
   public function withValue(string $value): self
   {
      $copy = clone $this;
      $copy->value = $value;
      return $copy;
   }

   /**
    * Return a copy with the expires.
    *
    * @param ?DateTimeInterface $expires
    * @return self
    */
   public function withExpires(?DateTimeInterface $expires): self
   {
      $copy = clone $this;
      $copy->expires = $expires;
      return $copy;
   }

   /**
    * Return a copy with the path.
    *
    * @param string $path
    * @return self
    */
   public function withPath(string $path): self
   {
      $copy = clone $this;
      $copy->path = $path;
      return $copy;
   }

   /**
    * Return a copy with the domain.
    *
    * @param ?string $domain
    * @return self
    */
   public function withDomain(?string $domain): self
   {
      $copy = clone $this;
      $copy->domain = $domain;
      return $copy;
   }

   /**
    * Return a copy with the secure.
    *
    * @param bool $secure
    * @return self
    */
   public function withSecure(bool $secure): self
   {
      $copy = clone $this;
      $copy->secure = $secure;
      return $copy;
   }

   /**
    * Return a copy with the http only.
    *
    * @param bool $httpOnly
    * @return self
    */
   public function withHttpOnly(bool $httpOnly): self
   {
      $copy = clone $this;
      $copy->httpOnly = $httpOnly;
      return $copy;
   }

   /**
    * Return a copy with the same site.
    *
    * @param ?string $sameSite
    * @return self
    */
   public function withSameSite(?string $sameSite): self
   {
      $copy = clone $this;
      $copy->sameSite = $sameSite;
      return $copy;
   }

   /**
    * Convert to header value.
    *
    * @return string
    */
   public function toHeaderValue(): string
   {
      $parts = [sprintf('%s=%s', $this->name, rawurlencode($this->value))];
      if ($this->expires !== null) {
         $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $this->expires->getTimestamp());
         $parts[] = 'Max-Age=' . max(0, $this->expires->getTimestamp() - time());
      }
      if ($this->path !== '') {
         $parts[] = 'Path=' . $this->path;
      }
      if ($this->domain !== null && $this->domain !== '') {
         $parts[] = 'Domain=' . $this->domain;
      }
      if ($this->secure) {
         $parts[] = 'Secure';
      }
      if ($this->httpOnly) {
         $parts[] = 'HttpOnly';
      }
      if ($this->sameSite !== null && $this->sameSite !== '') {
         $parts[] = 'SameSite=' . $this->sameSite;
      }

      return implode('; ', $parts);
   }
}



