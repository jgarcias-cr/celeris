<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Notification\DeliveryResult;
use Celeris\Framework\Notification\EmailMessage;
use Celeris\Framework\Notification\NotificationChannelInterface;
use Celeris\Framework\Notification\NotificationEnvelope;
use Celeris\Framework\Notification\NotificationManager;

/**
 * Represents the in-memory notification channel component for this file.
 */
final class InMemoryNotificationChannel implements NotificationChannelInterface
{
   /** @var array<int, NotificationEnvelope> */
   public array $sent = [];

   public function __construct(private string $channelName = 'memory')
   {
   }

   public function name(): string
   {
      return $this->channelName;
   }

   public function send(NotificationEnvelope $envelope): DeliveryResult
   {
      $this->sent[] = $envelope;
      return DeliveryResult::delivered($this->channelName, 'msg-' . count($this->sent), ['stored' => true]);
   }
}

/**
 * Handle assert true.
 *
 * @param string $label
 * @param bool $condition
 * @return void
 */
function assertTrue(string $label, bool $condition): void
{
   if (!$condition) {
      throw new RuntimeException($label);
   }
}

/**
 * Handle test null channel default.
 *
 * @return void
 */
function testNullChannelDefault(): void
{
   $config = new ConfigRepository([
      'notifications' => [
         'default_channel' => 'null',
         'channels' => [
            'null' => ['enabled' => true],
         ],
      ],
   ]);

   $manager = NotificationManager::fromConfig($config);
   $result = $manager->sendEmail(new EmailMessage('user@example.com', 'Welcome', 'Hello.'));

   assertTrue('null channel should deliver', $result->isDelivered());
   assertTrue('null channel should be selected', $result->channel() === 'null');
   assertTrue('null channel should discard payload', (bool) ($result->metadata()['discarded'] ?? false));
}

/**
 * Handle test custom channel.
 *
 * @return void
 */
function testCustomChannel(): void
{
   $channel = new InMemoryNotificationChannel('memory');
   $manager = new NotificationManager(['memory' => $channel], 'memory');

   $result = $manager->sendEmail(new EmailMessage('user@example.com', 'Welcome', 'Hello.'));

   assertTrue('custom channel should deliver', $result->isDelivered());
   assertTrue('custom channel should be selected', $result->channel() === 'memory');
   assertTrue('custom channel should capture envelopes', count($channel->sent) === 1);
}

/**
 * Handle test missing channel.
 *
 * @return void
 */
function testMissingChannel(): void
{
   $manager = new NotificationManager([], 'null');
   $result = $manager->sendEmail(new EmailMessage('user@example.com', 'Welcome', 'Hello.'), 'smtp');

   assertTrue('missing channel should fail', !$result->isDelivered());
   assertTrue('missing channel should reference requested name', $result->channel() === 'smtp');
}

/**
 * Handle test smtp remains external adapter.
 *
 * @return void
 */
function testSmtpRemainsExternalAdapter(): void
{
   $config = new ConfigRepository([
      'notifications' => [
         'default_channel' => 'null',
         'channels' => [
            'null' => ['enabled' => true],
            'smtp' => [
               'enabled' => true,
               'host' => '127.0.0.1',
            ],
         ],
      ],
   ]);

   $manager = NotificationManager::fromConfig($config);

   assertTrue('smtp channel should not be auto-registered in framework core', !$manager->hasChannel('smtp'));
}

/**
 * Handle test kernel dependency injection.
 *
 * @return void
 */
function testKernelDependencyInjection(): void
{
   $kernel = new Kernel(configLoader: null, hotReloadEnabled: false);
   $kernel->setConfigLoader(null);

   $kernel->routes()->get('/notify', function (NotificationManager $notifications): Response {
      $result = $notifications->sendEmail(new EmailMessage('user@example.com', 'Kernel Test', 'Hello from kernel.'));
      return new Response(
         $result->isDelivered() ? 200 : 500,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($result->toArray()),
      );
   });

   $request = new Request('GET', '/notify');
   $context = new RequestContext(bin2hex(random_bytes(8)), microtime(true), $request->getServerParams());
   $response = $kernel->handle($context, $request);
   $kernel->reset();

   assertTrue('kernel should resolve notification manager', $response->getStatus() === 200);
}

testNullChannelDefault();
testCustomChannel();
testMissingChannel();
testSmtpRemainsExternalAdapter();
testKernelDependencyInjection();

echo "notifications_validation: ok\n";
