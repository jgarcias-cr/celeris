<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Database\ORM\Event\EntityPersistedEvent;
use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Distributed\Auth\ServiceAuthenticator;
use Celeris\Framework\Distributed\Auth\ServicePrincipal;
use Celeris\Framework\Distributed\Auth\ServiceRequestSigner;
use Celeris\Framework\Distributed\Messaging\InMemoryMessageBus;
use Celeris\Framework\Distributed\Messaging\MessageEnvelope;
use Celeris\Framework\Distributed\MicroserviceRuntimeModel;
use Celeris\Framework\Distributed\Model\ModelLifecycleBridge;
use Celeris\Framework\Distributed\Observability\InMemoryObservabilityHook;
use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;
use Celeris\Framework\Distributed\Tracing\InMemoryTracer;
use Celeris\Framework\Distributed\Tracing\TraceContext;
use Celeris\Framework\Distributed\Tracing\W3CTraceContextPropagator;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Logging\LoggerInterface;

/**
 * Handle assert true.
 *
 * @param bool $condition
 * @param string $message
 * @return void
 */
function assertTrue(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

/**
 * Handle run multi service integration tests.
 *
 * @return void
 */
function runMultiServiceIntegrationTests(): void
{
   $bus = new InMemoryMessageBus();
   $tracer = new InMemoryTracer();
   $observability = new ObservabilityDispatcher();
   $hook = new InMemoryObservabilityHook();
   $observability->register($hook);

   $serviceA = new MicroserviceRuntimeModel(
      'service-a',
      'secret-a',
      new ServiceAuthenticator(['service-b' => 'secret-b']),
      $bus,
      $tracer,
      new W3CTraceContextPropagator(),
      $observability,
      false,
   );

   $serviceB = new MicroserviceRuntimeModel(
      'service-b',
      'secret-b',
      new ServiceAuthenticator(['service-a' => 'secret-a']),
      $bus,
      $tracer,
      new W3CTraceContextPropagator(),
      $observability,
      true,
   );

   $delivered = null;
   $bus->subscribe('orders.events', function (MessageEnvelope $envelope) use (&$delivered): void {
      $delivered = $envelope;
   });

   $entryRequest = new Request(
      'POST',
      '/api/orders',
      ['content-type' => 'application/json'],
      [],
      '{"order_id":42}'
   );

   $entryResponse = $serviceA->handle(
      new RequestContext('svc-a-req-1', microtime(true), ['REMOTE_ADDR' => '127.0.0.1']),
      $entryRequest,
      function (RequestContext $ctx, Request $request) use ($serviceA, $serviceB): Response {
         $outbound = $serviceA->prepareOutboundRequest(
            $ctx,
            new Request('POST', '/internal/orders/sync', ['content-type' => 'application/json'], [], $request->getBody())
         );

         return $serviceB->handle(
            new RequestContext('svc-b-req-1', microtime(true), ['REMOTE_ADDR' => '10.0.0.14']),
            $outbound,
            function (RequestContext $serviceBCtx, Request $serviceBRequest) use ($serviceB): Response {
               $principal = $serviceBCtx->getAttribute('service.principal');
               assertTrue($principal instanceof ServicePrincipal, 'Service B should resolve authenticated service principal.');
               assertTrue($principal->serviceId() === 'service-a', 'Service B should authenticate caller as service-a.');

               $serviceB->publishMessage(
                  $serviceBCtx,
                  'orders.events',
                  'orders.synced',
                  ['body_hash' => sha1($serviceBRequest->getBody())]
               );

               return new Response(202, ['content-type' => 'application/json'], '{"synced":true}');
            }
         );
      }
   );

   assertTrue($entryResponse->getStatus() === 202, 'Cross-service call should return accepted response.');
   assertTrue($delivered instanceof MessageEnvelope, 'Event-driven message should be published to bus.');
   if (!$delivered instanceof MessageEnvelope) {
      throw new RuntimeException('Event-driven message should be published to bus.');
   }
   assertTrue($delivered->topic() === 'orders.events', 'Published message should target expected topic.');
   assertTrue((string) $delivered->header('service') === 'service-b', 'Message should contain publishing service name.');

   $traceParent = (string) $delivered->header('traceparent', '');
   assertTrue($traceParent !== '', 'Published message should carry traceparent header.');

   $unauthorized = new Request(
      'POST',
      '/internal/orders/sync',
      [
         'x-service-id' => 'service-a',
         'x-service-ts' => (string) time(),
         'x-service-signature' => 'tampered-signature',
      ],
      [],
      '{"order_id":99}'
   );

   $unauthorizedResponse = $serviceB->handle(
      new RequestContext('svc-b-req-2', microtime(true), ['REMOTE_ADDR' => '10.0.0.15']),
      $unauthorized,
      static fn (RequestContext $ctx, Request $request): Response => new Response(200, [], 'unexpected'),
   );

   assertTrue($unauthorizedResponse->getStatus() === 401, 'Service auth middleware must reject invalid signatures.');

   // Model lifecycle events should bridge to messaging abstraction.
   $persistence = new PersistenceEventDispatcher();
   $lifecycleBridge = new ModelLifecycleBridge($persistence, $bus, 'model.lifecycle', $observability);
   $lifecycleBridge->bind();

   $entity = new class () {
      public int $id = 1;
   };
   $persistence->dispatch(new EntityPersistedEvent($entity));

   $lifecycleMessages = $bus->history('model.lifecycle');
   assertTrue(count($lifecycleMessages) === 1, 'Model lifecycle bridge should publish ORM lifecycle event.');
   assertTrue(
      $lifecycleMessages[0]->name() === 'model.lifecycle.entity_persisted',
      'Lifecycle message name should encode persistence event name.'
   );

   assertTrue(count($hook->eventsByName('request.start')) >= 2, 'Observability should capture request start events for both services.');
   assertTrue(count($hook->eventsByName('message.publish')) >= 1, 'Observability should capture message publish events.');
}

/**
 * Handle run tracing correctness tests.
 *
 * @return void
 */
function runTracingCorrectnessTests(): void
{
   $bus = new InMemoryMessageBus();
   $tracer = new InMemoryTracer();
   $propagator = new W3CTraceContextPropagator();
   $observability = new ObservabilityDispatcher();

   $runtime = new MicroserviceRuntimeModel(
      'inventory',
      'inventory-secret',
      new ServiceAuthenticator(['gateway' => 'gateway-secret']),
      $bus,
      $tracer,
      $propagator,
      $observability,
      true,
   );

   $incoming = new Request('GET', '/internal/stock', ['accept' => 'application/json'], ['sku' => 'A-42']);
   $callerTrace = TraceContext::root('gateway')->child('gateway');
   $withTrace = $propagator->injectRequest($incoming, $callerTrace);
   $signedWithTrace = (new ServiceRequestSigner('gateway', 'gateway-secret'))->sign($withTrace);

   $response = $runtime->handle(
      new RequestContext('inventory-req-1', microtime(true), ['REMOTE_ADDR' => '10.10.1.2']),
      $signedWithTrace,
      static fn (RequestContext $ctx, Request $request): Response => new Response(
         200,
         ['content-type' => 'application/json'],
         '{"stock":7}'
      ),
   );

   $traceParentResponse = $response->getHeader('traceparent');
   assertTrue(is_string($traceParentResponse) && $traceParentResponse !== '', 'Response should include propagated traceparent header.');

   $traceHeader = (string) $signedWithTrace->getHeader('traceparent');
   $requestParts = explode('-', $traceHeader);
   $responseParts = explode('-', (string) $traceParentResponse);

   assertTrue(count($requestParts) === 4, 'Request traceparent should have 4 sections.');
   assertTrue(count($responseParts) === 4, 'Response traceparent should have 4 sections.');
   assertTrue(
      $requestParts[1] === $responseParts[1],
      'Trace ID should remain stable across service boundary and response propagation.'
   );

   $spans = $tracer->finishedSpans();
   assertTrue(count($spans) >= 1, 'Tracer should capture at least one completed span.');

   $span = $spans[count($spans) - 1];
   assertTrue($span->context()->traceId() === $requestParts[1], 'Captured span should use inbound trace id.');
   assertTrue(($span->attributes()['http.status_code'] ?? null) === 200, 'Captured span should include final HTTP status attribute.');
}

/**
 * @return void
 */
function runLoggingSubsystemTests(): void
{
   $tmpRoot = '/tmp/celeris-phase11-logging-' . bin2hex(random_bytes(6));
   mkdir($tmpRoot . '/config', 0777, true);

   file_put_contents($tmpRoot . '/config/app.php', <<<'PHP'
<?php
return [
   'name' => 'Logging Test',
   'env' => 'test',
   'debug' => true,
];
PHP
);
   file_put_contents($tmpRoot . '/config/logging.php', <<<'PHP'
<?php
return [
   'path' => '/tmp/celeris-custom-log-path.log',
   'level' => 'debug',
];
PHP
);

   $kernel = new Kernel(
      configLoader: new ConfigLoader(
         $tmpRoot . '/config',
         new EnvironmentLoader(null, null, false, true),
      ),
      registerBuiltinRoutes: false,
   );
   $kernel->boot();

   $container = $kernel->getServiceContainer();
   $logger = $container->get(LoggerInterface::class);
   assertTrue($logger instanceof LoggerInterface, 'Logger interface should be resolvable from container.');

   $logger->info('logging test info', ['scope' => 'phase11']);
   $logger->error('logging test error', ['reason' => 'expected']);

   $defaultPath = $tmpRoot . '/var/log/app.log';
   assertTrue(is_file($defaultPath), 'Logger should write to fixed project path var/log/app.log.');
   $contents = (string) file_get_contents($defaultPath);
   assertTrue(str_contains($contents, '"message":"logging test info"'), 'Log file should include info message.');
   assertTrue(str_contains($contents, '"message":"logging test error"'), 'Log file should include error message.');
   assertTrue(!is_file('/tmp/celeris-custom-log-path.log'), 'Custom logging path config should be ignored.');
}

$checks = [
   'MultiServiceIntegration' => 'runMultiServiceIntegrationTests',
   'TracingCorrectness' => 'runTracingCorrectnessTests',
   'LoggingSubsystem' => 'runLoggingSubsystemTests',
];

$failed = false;
foreach ($checks as $name => $fn) {
   try {
      $fn();
      echo "[PASS] {$name}\n";
   } catch (Throwable $exception) {
      $failed = true;
      echo "[FAIL] {$name}: {$exception->getMessage()}\n";
   }
}

exit($failed ? 1 : 0);
