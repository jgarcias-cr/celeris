<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Purpose: implement email message behavior for the Notification subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by notification components when email message functionality is required.
 */
final class EmailMessage
{
   /** @var array<string, string> */
   private array $headers;
   /** @var array<int, string> */
   private array $tags;

   /**
    * @param array<string, string> $headers
    * @param array<int, string> $tags
    */
   public function __construct(
      private string $to,
      private string $subject,
      private ?string $textBody = null,
      private ?string $htmlBody = null,
      private ?string $fromAddress = null,
      private ?string $fromName = null,
      private ?string $replyTo = null,
      array $headers = [],
      array $tags = [],
   ) {
      $this->to = trim($this->to);
      $this->subject = trim($this->subject);
      $this->textBody = self::nullable($this->textBody);
      $this->htmlBody = self::nullable($this->htmlBody);
      $this->fromAddress = self::nullable($this->fromAddress);
      $this->fromName = self::nullable($this->fromName);
      $this->replyTo = self::nullable($this->replyTo);
      $this->headers = self::normalizeHeaders($headers);
      $this->tags = self::normalizeTags($tags);
   }

   /**
    * Handle to.
    *
    * @return string
    */
   public function to(): string
   {
      return $this->to;
   }

   /**
    * Handle subject.
    *
    * @return string
    */
   public function subject(): string
   {
      return $this->subject;
   }

   /**
    * Handle text body.
    *
    * @return ?string
    */
   public function textBody(): ?string
   {
      return $this->textBody;
   }

   /**
    * Handle html body.
    *
    * @return ?string
    */
   public function htmlBody(): ?string
   {
      return $this->htmlBody;
   }

   /**
    * Handle from address.
    *
    * @return ?string
    */
   public function fromAddress(): ?string
   {
      return $this->fromAddress;
   }

   /**
    * Handle from name.
    *
    * @return ?string
    */
   public function fromName(): ?string
   {
      return $this->fromName;
   }

   /**
    * Handle reply to.
    *
    * @return ?string
    */
   public function replyTo(): ?string
   {
      return $this->replyTo;
   }

   /**
    * @return array<string, string>
    */
   public function headers(): array
   {
      return $this->headers;
   }

   /**
    * @return array<int, string>
    */
   public function tags(): array
   {
      return $this->tags;
   }

   /**
    * Determine whether has body.
    *
    * @return bool
    */
   public function hasBody(): bool
   {
      return $this->textBody !== null || $this->htmlBody !== null;
   }

   /**
    * Return a copy with the text body.
    *
    * @param ?string $textBody
    * @return self
    */
   public function withTextBody(?string $textBody): self
   {
      $copy = clone $this;
      $copy->textBody = self::nullable($textBody);
      return $copy;
   }

   /**
    * Return a copy with the html body.
    *
    * @param ?string $htmlBody
    * @return self
    */
   public function withHtmlBody(?string $htmlBody): self
   {
      $copy = clone $this;
      $copy->htmlBody = self::nullable($htmlBody);
      return $copy;
   }

   /**
    * @param array<string, string> $headers
    * @return self
    */
   public function withHeaders(array $headers): self
   {
      $copy = clone $this;
      $copy->headers = self::normalizeHeaders($headers);
      return $copy;
   }

   /**
    * @param array<int, string> $tags
    * @return self
    */
   public function withTags(array $tags): self
   {
      $copy = clone $this;
      $copy->tags = self::normalizeTags($tags);
      return $copy;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'to' => $this->to,
         'subject' => $this->subject,
         'text_body' => $this->textBody,
         'html_body' => $this->htmlBody,
         'from_address' => $this->fromAddress,
         'from_name' => $this->fromName,
         'reply_to' => $this->replyTo,
         'headers' => $this->headers,
         'tags' => $this->tags,
      ];
   }

   /**
    * @param array<string, string> $headers
    * @return array<string, string>
    */
   private static function normalizeHeaders(array $headers): array
   {
      $normalized = [];
      foreach ($headers as $name => $value) {
         $header = trim((string) $name);
         if ($header === '') {
            continue;
         }
         $normalized[$header] = trim((string) $value);
      }

      return $normalized;
   }

   /**
    * @param array<int, string> $tags
    * @return array<int, string>
    */
   private static function normalizeTags(array $tags): array
   {
      $normalized = [];
      foreach ($tags as $tag) {
         $clean = trim((string) $tag);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }

   /**
    * @param ?string $value
    * @return ?string
    */
   private static function nullable(?string $value): ?string
   {
      if ($value === null) {
         return null;
      }

      $clean = trim($value);
      return $clean === '' ? null : $clean;
   }
}



