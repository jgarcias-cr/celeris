<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Connection\InMemoryQueryTracer;
use Celeris\Framework\Database\Connection\QueryTraceInspector;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\Migration\DatabaseMigrationRepository;
use Celeris\Framework\Database\Migration\MigrationInterface;
use Celeris\Framework\Database\Migration\MigrationRunner;
use Celeris\Framework\Database\ORM\Attribute\Column;
use Celeris\Framework\Database\ORM\Attribute\Entity;
use Celeris\Framework\Database\ORM\Attribute\Id;
use Celeris\Framework\Database\ORM\Attribute\LazyRelation;
use Celeris\Framework\Database\ORM\EntityManager;
use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Database\ORM\LazyReference;
use Celeris\Framework\Database\ORM\OrmException;
use Celeris\Framework\Database\Testing\ArrayConnection;
use Celeris\Framework\Domain\Event\AbstractDomainEvent;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;

#[Entity(table: 'p8_users')]
/**
 * Represents the phase8 user component for this file.
 */
final class Phase8User
{
   #[Id(generated: false)]
   #[Column('id')]
   public int $id;

   #[Column('name')]
   public string $name;
}

#[Entity(table: 'p8_posts')]
/**
 * Represents the phase8 post component for this file.
 */
final class Phase8Post
{
   #[Id(generated: false)]
   #[Column('id')]
   public int $id;

   #[Column('title')]
   public string $title;

   #[Column('user_id')]
   public int $userId;

   #[LazyRelation(targetEntity: Phase8User::class, localKey: 'userId', targetKey: 'id')]
   public LazyReference $author;
}

/**
 * Represents the phase8 order placed event component for this file.
 */
final class Phase8OrderPlacedEvent extends AbstractDomainEvent
{
   /**
    * Create a new instance.
    *
    * @param int $userId
    * @return mixed
    */
   public function __construct(private int $userId)
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
      return ['user_id' => $this->userId];
   }
}

#[Entity(table: 'p8_users_tx')]
/**
 * Represents the phase8 user aggregate component for this file.
 */
final class Phase8UserAggregate
{
   /** @var array<int, Phase8OrderPlacedEvent> */
   private array $domainEvents = [];

   #[Id(generated: false)]
   #[Column('id')]
   public int $id;

   #[Column('name')]
   public ?string $name;

   /**
    * Create a new instance.
    *
    * @param int $id
    * @param ?string $name
    * @return mixed
    */
   public function __construct(int $id, ?string $name)
   {
      $this->id = $id;
      $this->name = $name;
      $this->domainEvents[] = new Phase8OrderPlacedEvent($id);
   }

   /**
    * @return array<int, Phase8OrderPlacedEvent>
    */
   public function pullDomainEvents(): array
   {
      $events = $this->domainEvents;
      $this->domainEvents = [];
      return $events;
   }
}

#[Entity(table: 'p8_seq_users')]
/**
 * Represents a sequence-backed entity used to validate id strategy behavior.
 */
final class Phase8SequenceUser
{
   #[Id(generated: true, strategy: 'sequence', sequence: 'p8_seq_users_id_seq')]
   #[Column('id')]
   public int $id;

   #[Column('name')]
   public string $name;
}

/**
 * Represents the create users migration component for this file.
 */
final class CreateUsersMigration implements MigrationInterface
{
   /**
    * Handle version.
    *
    * @return string
    */
   public function version(): string
   {
      return '20260211_000001';
   }

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string
   {
      return 'Create phase8 users table';
   }

   /**
    * Handle up.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function up(ConnectionInterface $connection): void
   {
      $connection->execute('CREATE TABLE IF NOT EXISTS p8_m_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
   }

   /**
    * Handle down.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function down(ConnectionInterface $connection): void
   {
      // No-op for array driver.
   }
}

/**
 * Represents the create posts migration component for this file.
 */
final class CreatePostsMigration implements MigrationInterface
{
   /**
    * Handle version.
    *
    * @return string
    */
   public function version(): string
   {
      return '20260211_000002';
   }

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string
   {
      return 'Create phase8 posts table';
   }

   /**
    * Handle up.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function up(ConnectionInterface $connection): void
   {
      $connection->execute('CREATE TABLE IF NOT EXISTS p8_m_posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, user_id INTEGER NOT NULL)');
   }

   /**
    * Handle down.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function down(ConnectionInterface $connection): void
   {
      // No-op for array driver.
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
 * Handle run query tracing tests.
 *
 * @return void
 */
function runQueryTracingTests(): void
{
   $tracer = new InMemoryQueryTracer();
   $connection = new ArrayConnection('trace', $tracer);

   $pool = new ConnectionPool();
   $pool->addConnection('default', $connection);
   $dbal = new DBAL($pool);

   $connection->execute('CREATE TABLE IF NOT EXISTS trace_items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

   $insert = $dbal->queryBuilder()
      ->insert('trace_items', ['id' => 1, 'name' => 'alpha'])
      ->build();
   $connection->execute($insert->sql(), $insert->params());

   $snapshot = count($tracer->all());

   $select = $dbal->queryBuilder()
      ->select(['id', 'name'])
      ->from('trace_items')
      ->where('id = :id', ['id' => 1])
      ->limit(1)
      ->build();

   $row = $connection->fetchOne($select->sql(), $select->params());
   assertTrue(is_array($row) && ($row['name'] ?? null) === 'alpha', 'DBAL query builder should return inserted row.');

   $inspector = new QueryTraceInspector($tracer);
   $newEntries = $inspector->queriesSince($snapshot);
   assertTrue(count($newEntries) === 1, 'Exactly one query should be traced after snapshot.');
   assertTrue(str_starts_with(strtoupper($newEntries[0]->sql()), 'SELECT'), 'Traced query should be SELECT.');
}

/**
 * Handle run zero hidden query enforcement tests.
 *
 * @return void
 */
function runZeroHiddenQueryEnforcementTests(): void
{
   $tracer = new InMemoryQueryTracer();
   $connection = new ArrayConnection('orm', $tracer);

   $connection->execute('CREATE TABLE IF NOT EXISTS p8_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
   $connection->execute('CREATE TABLE IF NOT EXISTS p8_posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, user_id INTEGER NOT NULL)');
   $connection->execute('INSERT INTO p8_users (id, name) VALUES (:id, :name)', ['id' => 7, 'name' => 'Ari']);
   $connection->execute('INSERT INTO p8_posts (id, title, user_id) VALUES (:id, :title, :user_id)', ['id' => 70, 'title' => 'Hello', 'user_id' => 7]);

   $em = new EntityManager($connection);

   $post = $em->find(Phase8Post::class, 70);
   assertTrue($post instanceof Phase8Post, 'EntityManager::find should hydrate entity.');

   $inspector = new QueryTraceInspector($tracer);
   $snapshot = $inspector->snapshot();

   $authorRef = $post->author;
   assertTrue($authorRef instanceof LazyReference, 'Lazy relation should hydrate as LazyReference.');
   $inspector->assertNoQueriesSince($snapshot, 'Hidden query executed while reading lazy relation reference.');

   $author = $em->loadRelation($post, 'author');
   assertTrue($author instanceof Phase8User, 'Explicit relation load should resolve target entity.');
   $afterLoad = $inspector->queriesSince($snapshot);
   assertTrue(count($afterLoad) === 1, 'Exactly one query should be executed for explicit relation load.');
}

/**
 * Handle run transaction determinism tests.
 *
 * @return void
 */
function runTransactionDeterminismTests(): void
{
   $connection = new ArrayConnection('tx');
   $connection->execute('CREATE TABLE IF NOT EXISTS p8_users_tx (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

   $persistenceEvents = new PersistenceEventDispatcher();
   $domainEvents = new DomainEventDispatcher();

   $sequence = [];

   $persistenceEvents->listen('*', function ($event) use (&$sequence): void {
      $sequence[] = 'p:' . $event->name();
   });

   $domainEvents->listen('*', function ($event) use (&$sequence): void {
      $sequence[] = 'd:' . $event->eventName();
   });

   $em = new EntityManager($connection, null, $persistenceEvents, $domainEvents);

   $em->persist(new Phase8UserAggregate(1, 'Kora'));
   $em->flush();

   assertTrue($connection->transactionLog() === ['begin', 'commit'], 'Successful flush must produce deterministic begin/commit transaction log.');
   assertTrue(
      $sequence === ['p:pre_flush', 'p:entity_persisting', 'p:entity_persisted', 'd:Phase8OrderPlacedEvent', 'p:post_flush'],
      'Persistence and domain events must have deterministic and separated ordering.'
   );

   try {
      $em->persist(new Phase8UserAggregate(2, null));
      $em->flush();
      throw new RuntimeException('Expected flush with null name to fail.');
   } catch (OrmException) {
      // Expected.
   }

   assertTrue(
      $connection->transactionLog() === ['begin', 'commit', 'begin', 'rollback'],
      'Failing flush must deterministically roll back transaction.'
   );
}

/**
 * Handle run migration determinism tests.
 *
 * @return void
 */
function runMigrationDeterminismTests(): void
{
   $connection = new ArrayConnection('migration');
   $repository = new DatabaseMigrationRepository($connection);
   $runner = new MigrationRunner($connection, $repository);

   $migrations = [new CreatePostsMigration(), new CreateUsersMigration()];
   $result = $runner->migrate($migrations);

   assertTrue(
      $result->applied() === ['20260211_000001', '20260211_000002'],
      'Migrations should apply in deterministic version order regardless of registration order.'
   );

   $rollback = $runner->rollback($migrations, 1);
   assertTrue(
      $rollback->rolledBack() === ['20260211_000002'],
      'Rollback should revert latest applied migration deterministically.'
   );
}

/**
 * Handle run generated id strategy tests.
 *
 * @return void
 */
function runGeneratedIdStrategyTests(): void
{
   $connection = new ArrayConnection('seq');
   $connection->execute('CREATE TABLE IF NOT EXISTS p8_seq_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

   $em = new EntityManager($connection);

   $alpha = new Phase8SequenceUser();
   $alpha->name = 'Alpha';
   $em->persist($alpha);
   $em->flush();

   $beta = new Phase8SequenceUser();
   $beta->name = 'Beta';
   $em->persist($beta);
   $em->flush();

   assertTrue($alpha->id === 1, 'Sequence strategy should assign the first generated id before insert.');
   assertTrue($beta->id === 2, 'Sequence strategy should increment id on subsequent inserts.');

   $rows = $connection->tables()['p8_seq_users'] ?? [];
   assertTrue(count($rows) === 2, 'Sequence strategy inserts should persist both rows.');
   assertTrue(($rows[0]['id'] ?? null) === 1 && ($rows[1]['id'] ?? null) === 2, 'Persisted ids should match generated sequence values.');
}

$checks = [
   'QueryTracing' => 'runQueryTracingTests',
   'ZeroHiddenQueries' => 'runZeroHiddenQueryEnforcementTests',
   'TransactionDeterminism' => 'runTransactionDeterminismTests',
   'MigrationDeterminism' => 'runMigrationDeterminismTests',
   'GeneratedIdStrategy' => 'runGeneratedIdStrategyTests',
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

