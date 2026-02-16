<?php

declare(strict_types=1);

require __DIR__ . '/../../framework/src/bootstrap.php';
require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;
use Celeris\Notification\RealtimeGateway\Http\HttpGatewayResponse;
use Celeris\Notification\RealtimeGateway\Http\HttpGatewayTransportInterface;
use Celeris\Notification\RealtimeGateway\HttpRealtimeGatewayClient;
use Celeris\Notification\RealtimeGateway\RealtimeEventMessage;
use Celeris\Notification\RealtimeGateway\RealtimeFailureType;
use Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider;

final class FakeGatewayTransport implements HttpGatewayTransportInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function __construct(private HttpGatewayResponse|Throwable $next)
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpGatewayResponse
    {
        $this->calls[] = [
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout_seconds' => $timeoutSeconds,
        ];

        if ($this->next instanceof Throwable) {
            throw $this->next;
        }

        return $this->next;
    }
}

function assertTrue(string $label, bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function testHttpClientSuccessAndAuthHeaders(): void
{
    $transport = new FakeGatewayTransport(new HttpGatewayResponse(202, '{"ok":true}', ['retry-after' => '2']));
    $client = new HttpRealtimeGatewayClient(
        endpoint: 'http://gateway.test/publish',
        timeoutSeconds: 3,
        serviceId: 'svc-api',
        serviceSecret: 'secret-123',
        transport: $transport,
    );

    $message = new RealtimeEventMessage(
        event: 'notification.created',
        userId: '42',
        payload: ['notification_id' => 'n-1'],
        idempotencyKey: 'idem-1',
        traceId: 'trace-1',
    );

    $result = $client->publish($message);

    assertTrue('publish should be successful for 2xx', $result->isPublished());
    assertTrue('status code should be 202', $result->statusCode() === 202);
    assertTrue('transport should be called once', count($transport->calls) === 1);

    $headers = $transport->calls[0]['headers'];
    assertTrue('service id header should be present', isset($headers['x-celeris-service-id']) && $headers['x-celeris-service-id'] === 'svc-api');
    assertTrue('signature header should be present', isset($headers['x-celeris-signature']) && trim((string) $headers['x-celeris-signature']) !== '');
}

function testRetryableAndTerminalClassification(): void
{
    $retryClient = new HttpRealtimeGatewayClient(
        endpoint: 'http://gateway.test/publish',
        transport: new FakeGatewayTransport(new HttpGatewayResponse(503, 'busy')),
    );

    $terminalClient = new HttpRealtimeGatewayClient(
        endpoint: 'http://gateway.test/publish',
        transport: new FakeGatewayTransport(new HttpGatewayResponse(401, 'unauthorized')),
    );

    $message = RealtimeEventMessage::create('notification.created', '42', ['x' => 'y']);

    $retry = $retryClient->publish($message);
    assertTrue('503 should be classified as retryable', !$retry->isPublished() && $retry->failureType() === RealtimeFailureType::RETRYABLE);

    $terminal = $terminalClient->publish($message);
    assertTrue('401 should be classified as terminal', !$terminal->isPublished() && $terminal->failureType() === RealtimeFailureType::TERMINAL);
}

function testProviderRegistration(): void
{
    $configDisabled = new ConfigRepository([
        'notifications' => [
            'realtime' => [
                'enabled' => false,
            ],
        ],
    ]);

    $services = new ServiceRegistry();
    $services->singleton(ConfigRepository::class, static fn (ContainerInterface $c): ConfigRepository => $configDisabled);

    $provider = new RealtimeGatewayServiceProvider();
    $provider->register($services);

    $container = new Container($services->all());
    $container->validateCircularDependencies();

    $client = $container->get(RealtimeGatewayClientInterface::class);
    assertTrue('provider should register gateway client interface', $client instanceof RealtimeGatewayClientInterface);

    $result = $client->publish(RealtimeEventMessage::create('notification.created', '42', []));
    assertTrue('disabled realtime config should return non-published result', !$result->isPublished());
}

testHttpClientSuccessAndAuthHeaders();
testRetryableAndTerminalClassification();
testProviderRegistration();

echo "realtime_gateway_validation: ok\n";
