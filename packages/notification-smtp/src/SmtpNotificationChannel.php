<?php

declare(strict_types=1);

namespace Celeris\Notification\Smtp;

use Celeris\Framework\Notification\DeliveryResult;
use Celeris\Framework\Notification\NotificationChannelInterface;
use Celeris\Framework\Notification\NotificationEnvelope;
use Throwable;

/**
 * Purpose: implement smtp notification channel behavior for the Notification subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by notification components when smtp notification channel functionality is required.
 */
final class SmtpNotificationChannel implements NotificationChannelInterface
{
   public function __construct(
      private string $host,
      private int $port = 587,
      private string $encryption = 'tls',
      private string $username = '',
      private string $password = '',
      private ?string $fromAddress = null,
      private ?string $fromName = null,
      private int $timeoutSeconds = 10,
      private string $ehloDomain = 'localhost',
   ) {
      $this->host = trim($this->host);
      $this->port = max(1, min($this->port, 65535));
      $this->encryption = self::normalizeEncryption($this->encryption);
      $this->username = trim($this->username);
      $this->password = trim($this->password);
      $this->fromAddress = self::nullable($this->fromAddress);
      $this->fromName = self::nullable($this->fromName);
      $this->timeoutSeconds = max(1, $this->timeoutSeconds);
      $this->ehloDomain = self::sanitizeDomain($this->ehloDomain);
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'smtp';
   }

   /**
    * Handle send.
    *
    * @param NotificationEnvelope $envelope
    * @return DeliveryResult
    */
   public function send(NotificationEnvelope $envelope): DeliveryResult
   {
      try {
         if ($this->host === '') {
            return DeliveryResult::failed($this->name(), 'SMTP host is not configured.');
         }

         $message = $envelope->emailMessage();
         if ($message === null) {
            return DeliveryResult::failed($this->name(), 'SMTP channel supports only email notifications.');
         }
         if (!$message->hasBody()) {
            return DeliveryResult::failed($this->name(), 'Email body is required.');
         }

         $to = $this->sanitizeMailbox($message->to());
         if ($to === '') {
            return DeliveryResult::failed($this->name(), 'Recipient address is required.');
         }

         $subject = trim($message->subject());
         if ($subject === '') {
            return DeliveryResult::failed($this->name(), 'Email subject is required.');
         }

         $fromAddress = $this->sanitizeMailbox($message->fromAddress() ?? $this->fromAddress ?? '');
         if ($fromAddress === '') {
            return DeliveryResult::failed($this->name(), 'Sender address is not configured.');
         }

         $fromName = self::nullable($message->fromName() ?? $this->fromName);
         $replyTo = $this->sanitizeMailbox($message->replyTo() ?? '');
         $payload = $this->composeMimeMessage(
            to: $to,
            subject: $subject,
            textBody: $message->textBody(),
            htmlBody: $message->htmlBody(),
            fromAddress: $fromAddress,
            fromName: $fromName,
            replyTo: $replyTo !== '' ? $replyTo : null,
            customHeaders: $message->headers(),
         );

         [$code, $response] = $this->sendRaw($fromAddress, $to, $payload);
         if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed($this->name(), 'SMTP delivery failed: ' . $response, ['smtp_response' => $response]);
         }

         $providerMessageId = $this->extractProviderMessageId($response);
         return DeliveryResult::delivered($this->name(), $providerMessageId, [
            'recipient' => $to,
            'smtp_response' => $response,
         ]);
      } catch (Throwable $exception) {
         return DeliveryResult::failed($this->name(), 'SMTP error: ' . $exception->getMessage());
      }
   }

   /**
    * @return array{0: int, 1: string}
    */
   private function sendRaw(string $fromAddress, string $toAddress, string $payload): array
   {
      $transport = $this->buildTransportTarget();
      $stream = @stream_socket_client(
         $transport,
         $errorCode,
         $errorMessage,
         $this->timeoutSeconds,
         STREAM_CLIENT_CONNECT
      );

      if (!is_resource($stream)) {
         throw new \RuntimeException(sprintf('Unable to connect to SMTP server (%s:%d): %s', $this->host, $this->port, (string) $errorMessage));
      }

      stream_set_timeout($stream, $this->timeoutSeconds);

      try {
         $this->expect($stream, [220], 'SMTP greeting');
         $this->command($stream, 'EHLO ' . $this->ehloDomain, [250], 'EHLO');

         if ($this->encryption === 'tls' || $this->encryption === 'starttls') {
            $this->command($stream, 'STARTTLS', [220], 'STARTTLS');
            $cryptoOk = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
               throw new \RuntimeException('Failed to enable TLS for SMTP connection.');
            }
            $this->command($stream, 'EHLO ' . $this->ehloDomain, [250], 'EHLO after STARTTLS');
         }

         if ($this->username !== '') {
            $this->authenticateLogin($stream);
         }

         $this->command($stream, 'MAIL FROM:<' . $fromAddress . '>', [250], 'MAIL FROM');
         $this->command($stream, 'RCPT TO:<' . $toAddress . '>', [250, 251], 'RCPT TO');
         $this->command($stream, 'DATA', [354], 'DATA');

         $this->write($stream, $this->dotStuff($payload) . "\r\n.\r\n");
         [$code, $line] = $this->expect($stream, [250], 'message body');

         $this->command($stream, 'QUIT', [221], 'QUIT');

         return [$code, $line];
      } finally {
         fclose($stream);
      }
   }

   private function authenticateLogin($stream): void
   {
      $this->command($stream, 'AUTH LOGIN', [334], 'AUTH LOGIN');
      $this->command($stream, base64_encode($this->username), [334], 'AUTH USERNAME');
      $this->command($stream, base64_encode($this->password), [235], 'AUTH PASSWORD');
   }

   /**
    * @param array<int, int> $expectedCodes
    * @return array{0: int, 1: string}
    */
   private function command($stream, string $command, array $expectedCodes, string $step): array
   {
      $this->write($stream, $command . "\r\n");
      return $this->expect($stream, $expectedCodes, $step);
   }

   private function write($stream, string $chunk): void
   {
      $result = fwrite($stream, $chunk);
      if ($result === false || $result === 0) {
         throw new \RuntimeException('Failed writing data to SMTP stream.');
      }
   }

   /**
    * @param array<int, int> $expectedCodes
    * @return array{0: int, 1: string}
    */
   private function expect($stream, array $expectedCodes, string $step): array
   {
      [$code, $line] = $this->readResponse($stream);
      if (!in_array($code, $expectedCodes, true)) {
         throw new \RuntimeException(sprintf('Unexpected SMTP response during %s: [%d] %s', $step, $code, $line));
      }

      return [$code, $line];
   }

   /**
    * @return array{0: int, 1: string}
    */
   private function readResponse($stream): array
   {
      $line = '';

      while (!feof($stream)) {
         $chunk = fgets($stream, 8192);
         if ($chunk === false) {
            break;
         }

         $line .= $chunk;
         if (strlen($chunk) < 4) {
            continue;
         }

         if ($chunk[3] === ' ') {
            break;
         }
      }

      $line = trim($line);
      if ($line === '' || !preg_match('/^(\d{3})/', $line, $matches)) {
         throw new \RuntimeException('Malformed SMTP response.');
      }

      return [(int) $matches[1], $line];
   }

   /**
    * @param array<string, string> $customHeaders
    */
   private function composeMimeMessage(
      string $to,
      string $subject,
      ?string $textBody,
      ?string $htmlBody,
      string $fromAddress,
      ?string $fromName,
      ?string $replyTo,
      array $customHeaders,
   ): string {
      $headers = [];
      $headers[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000';
      $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '.' . time() . '@' . $this->ehloDomain . '>';
      $headers[] = 'From: ' . $this->formatMailbox($fromAddress, $fromName);
      $headers[] = 'To: ' . $to;
      $headers[] = 'Subject: ' . $this->sanitizeHeaderValue($subject);
      $headers[] = 'MIME-Version: 1.0';
      if ($replyTo !== null && $replyTo !== '') {
         $headers[] = 'Reply-To: ' . $replyTo;
      }

      foreach ($customHeaders as $name => $value) {
         $normalizedName = trim((string) $name);
         if ($normalizedName === '') {
            continue;
         }
         $headers[] = $normalizedName . ': ' . $this->sanitizeHeaderValue((string) $value);
      }

      $text = $textBody !== null ? $this->normalizeBody($textBody) : null;
      $html = $htmlBody !== null ? $this->normalizeBody($htmlBody) : null;

      if ($text !== null && $html !== null) {
         $boundary = 'b-' . bin2hex(random_bytes(12));
         $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

         $body = [];
         $body[] = '--' . $boundary;
         $body[] = 'Content-Type: text/plain; charset=UTF-8';
         $body[] = 'Content-Transfer-Encoding: 8bit';
         $body[] = '';
         $body[] = $text;
         $body[] = '--' . $boundary;
         $body[] = 'Content-Type: text/html; charset=UTF-8';
         $body[] = 'Content-Transfer-Encoding: 8bit';
         $body[] = '';
         $body[] = $html;
         $body[] = '--' . $boundary . '--';

         return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
      }

      if ($html !== null) {
         $headers[] = 'Content-Type: text/html; charset=UTF-8';
         $headers[] = 'Content-Transfer-Encoding: 8bit';
         return implode("\r\n", $headers) . "\r\n\r\n" . $html;
      }

      $headers[] = 'Content-Type: text/plain; charset=UTF-8';
      $headers[] = 'Content-Transfer-Encoding: 8bit';
      return implode("\r\n", $headers) . "\r\n\r\n" . ($text ?? '');
   }

   private function dotStuff(string $payload): string
   {
      $normalized = str_replace("\r\n", "\n", $payload);
      $normalized = str_replace("\r", "\n", $normalized);
      $normalized = preg_replace('/^\./m', '..', $normalized) ?? $normalized;

      return str_replace("\n", "\r\n", $normalized);
   }

   private function normalizeBody(string $value): string
   {
      $normalized = str_replace("\r\n", "\n", $value);
      $normalized = str_replace("\r", "\n", $normalized);
      return str_replace("\n", "\r\n", $normalized);
   }

   private function sanitizeHeaderValue(string $value): string
   {
      return trim(str_replace(["\r", "\n"], ' ', $value));
   }

   private function formatMailbox(string $address, ?string $name): string
   {
      if ($name === null || trim($name) === '') {
         return $address;
      }

      return '"' . addcslashes($this->sanitizeHeaderValue($name), '"\\') . "\" <{$address}>";
   }

   private function sanitizeMailbox(string $address): string
   {
      $clean = trim(str_replace(["\r", "\n"], '', $address));
      return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : '';
   }

   private function extractProviderMessageId(string $response): ?string
   {
      if (preg_match('/(?:queued as|id=)\s*([A-Za-z0-9._:-]+)/i', $response, $matches) === 1) {
         return trim((string) ($matches[1] ?? '')) ?: null;
      }

      return null;
   }

   private function buildTransportTarget(): string
   {
      if ($this->encryption === 'ssl') {
         return sprintf('ssl://%s:%d', $this->host, $this->port);
      }

      return sprintf('tcp://%s:%d', $this->host, $this->port);
   }

   private static function normalizeEncryption(string $encryption): string
   {
      $normalized = strtolower(trim($encryption));
      return match ($normalized) {
         'ssl' => 'ssl',
         'starttls' => 'starttls',
         'none', '' => 'none',
         default => 'tls',
      };
   }

   private static function sanitizeDomain(string $domain): string
   {
      $clean = strtolower(trim($domain));
      if ($clean === '') {
         return 'localhost';
      }

      $clean = str_replace(["\r", "\n", ' '], '', $clean);
      return $clean !== '' ? $clean : 'localhost';
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
