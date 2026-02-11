<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Domain\DomainState;
use Celeris\Framework\Domain\Event\AbstractDomainEvent;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;
use Celeris\Framework\Http\HttpStatus;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Serialization\Attribute\Dto;
use Celeris\Framework\Serialization\Attribute\Ignore;
use Celeris\Framework\Serialization\Attribute\SerializeName;
use Celeris\Framework\Serialization\Serializer;
use Celeris\Framework\Validation\Attribute\Email;
use Celeris\Framework\Validation\Attribute\InList;
use Celeris\Framework\Validation\Attribute\Length;
use Celeris\Framework\Validation\Attribute\Range;
use Celeris\Framework\Validation\Attribute\Required;
use Celeris\Framework\Validation\ValidatorEngine;

#[Dto]
/**
 * Represents the create order dto component for this file.
 */
final class CreateOrderDto
{
   /**
    * Create a new instance.
    *
    * @param #[Required] #[Email] string $email
    * @param #[Required] #[Length(min: 3, max: 32)] string $sku
    * @param #[Range(min: 1, max: 1000)] int $quantity
    * @param #[InList(['draft', 'active', 'suspended'])] string $state
    * @return mixed
    */
   public function __construct(
      #[Required]
      #[Email]
      public string $email,
      #[Required]
      #[Length(min: 3, max: 32)]
      public string $sku,
      #[Range(min: 1, max: 1000)]
      public int $quantity,
      #[InList(['draft', 'active', 'suspended'])]
      public string $state = 'draft',
   ) {
   }
}

/**
 * Represents the order projection component for this file.
 */
final class OrderProjection
{
   #[SerializeName('order_id')]
   public string $id = 'o-1';

   public string $status = 'active';

   #[Ignore]
   public string $internal = 'do-not-serialize';
}

/**
 * Represents the order created event component for this file.
 */
final class OrderCreatedEvent extends AbstractDomainEvent
{
   /**
    * Create a new instance.
    *
    * @param string $orderId
    * @return mixed
    */
   public function __construct(private string $orderId)
   {
      parent::__construct();
   }

   /**
    * Handle payload.
    *
    * @return array
    */
   public function payload(): array
   {
      return ['order_id' => $this->orderId];
   }
}

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
 * Handle build kernel.
 *
 * @return Kernel
 */
function buildKernel(): Kernel
{
   $kernel = new Kernel(configLoader: null, hotReloadEnabled: false);
   $kernel->setConfigLoader(null);

   $kernel->routes()->post('/orders', function (CreateOrderDto $dto): Response {
      return new Response(HttpStatus::CREATED, ['content-type' => 'application/json; charset=utf-8'], json_encode([
         'email' => $dto->email,
         'sku' => $dto->sku,
         'quantity' => $dto->quantity,
         'state' => $dto->state,
      ], JSON_UNESCAPED_UNICODE));
   });

   return $kernel;
}

/**
 * Handle request.
 *
 * @param Kernel $kernel
 * @param Request $request
 * @return Response
 */
function request(Kernel $kernel, Request $request): Response
{
   $ctx = new RequestContext(bin2hex(random_bytes(8)), microtime(true), $request->getServerParams());
   $response = $kernel->handle($ctx, $request);
   $kernel->reset();
   return $response;
}

/**
 * Handle run payload validation tests.
 *
 * @return void
 */
function runPayloadValidationTests(): void
{
   $kernel = buildKernel();

   $invalid = request($kernel, new Request(
      'POST',
      '/orders',
      ['content-type' => 'application/json'],
      [],
      '',
      [],
      [],
      [
         'email' => 'not-an-email',
         'sku' => 'ab',
         'quantity' => 0,
         'state' => 'unknown',
      ],
      ['REMOTE_ADDR' => '127.0.0.1'],
   ));

   assertTrue($invalid->getStatus() === HttpStatus::UNPROCESSABLE_ENTITY->value, 'Invalid payload should return 422.');
   $decoded = json_decode($invalid->getBody(), true);
   assertTrue(is_array($decoded), 'Validation error response must be JSON object.');
   assertTrue(isset($decoded['errors']) && is_array($decoded['errors']) && count($decoded['errors']) >= 4, 'Validation response must include detailed errors.');

   $valid = request($kernel, new Request(
      'POST',
      '/orders',
      ['content-type' => 'application/json'],
      [],
      '',
      [],
      [],
      [
         'email' => 'buyer@example.com',
         'sku' => 'SKU-001',
         'quantity' => 2,
         'state' => 'active',
      ],
      ['REMOTE_ADDR' => '127.0.0.1'],
   ));

   assertTrue($valid->getStatus() === HttpStatus::CREATED->value, 'Valid payload should return 201.');
}

/**
 * Handle run schema compliance tests.
 *
 * @return void
 */
function runSchemaComplianceTests(): void
{
   $validator = new ValidatorEngine();

   $schema = [
      'id' => ['type' => 'string', 'required' => true, 'min_length' => 3],
      'quantity' => ['type' => 'int', 'required' => true, 'min' => 1],
      'state' => ['type' => 'string', 'required' => true],
   ];

   $invalidPayload = [
      'id' => 'x',
      'quantity' => 0,
      'state' => 'draft',
      'extra' => true,
   ];

   $invalid = $validator->validateSchema($invalidPayload, $schema, false);
   assertTrue(!$invalid->isValid(), 'Schema validator must reject invalid payload.');
   assertTrue(count($invalid->errors()) >= 3, 'Schema validator must report all relevant errors.');

   $validPayload = [
      'id' => 'ord-1',
      'quantity' => 5,
      'state' => 'active',
   ];

   $valid = $validator->validateSchema($validPayload, $schema, false);
   assertTrue($valid->isValid(), 'Schema validator must accept compliant payload.');
}

/**
 * Handle run serialization determinism tests.
 *
 * @return void
 */
function runSerializationDeterminismTests(): void
{
   $serializer = new Serializer();

   $jsonA = $serializer->toJson(['b' => 2, 'a' => 1]);
   $jsonB = $serializer->toJson(['a' => 1, 'b' => 2]);
   assertTrue($jsonA === $jsonB, 'Associative serialization must be deterministic.');

   $projection = new OrderProjection();
   $projectionJson = $serializer->toJson($projection);
   assertTrue($projectionJson === '{"order_id":"o-1","status":"active"}', 'Object serialization must be deterministic and honor attributes.');
}

/**
 * Handle run domain event dispatcher tests.
 *
 * @return void
 */
function runDomainEventDispatcherTests(): void
{
   $dispatcher = new DomainEventDispatcher();
   $events = [];

   $dispatcher->listen('*', function (OrderCreatedEvent $event) use (&$events): void {
      $events[] = 'all:' . $event->payload()['order_id'];
   });
   $dispatcher->listen(OrderCreatedEvent::class, function (OrderCreatedEvent $event) use (&$events): void {
      $events[] = 'typed:' . $event->payload()['order_id'];
   });

   $dispatcher->dispatch(new OrderCreatedEvent('ord-42'));

   assertTrue($events === ['all:ord-42', 'typed:ord-42'], 'Event dispatcher ordering must be deterministic.');
   assertTrue($dispatcher->history() !== [], 'Event dispatcher must retain history.');
}

/**
 * Handle run enum tests.
 *
 * @return void
 */
function runEnumTests(): void
{
   assertTrue(HttpStatus::NOT_FOUND->value === 404, 'HTTP status enum should expose stable numeric value.');
   assertTrue(HttpStatus::NOT_FOUND->reasonPhrase() === 'Not Found', 'HTTP status enum should expose reason phrase.');
   assertTrue(DomainState::ACTIVE->value === 'active', 'Domain state enum should expose canonical value.');
}

$checks = [
   'PayloadValidation' => 'runPayloadValidationTests',
   'SchemaCompliance' => 'runSchemaComplianceTests',
   'SerializationDeterminism' => 'runSerializationDeterminismTests',
   'DomainEventDispatcher' => 'runDomainEventDispatcherTests',
   'Enums' => 'runEnumTests',
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


