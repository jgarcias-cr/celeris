<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\ActiveRecord\ActiveRecordManager;
use Celeris\Framework\Database\ActiveRecord\ActiveRecordModel;
use Celeris\Framework\Database\ActiveRecord\ActiveRecordQuery;
use Celeris\Framework\Database\ActiveRecord\ActiveRecordServiceProvider;
use Celeris\Framework\Database\ActiveRecord\Exception\ValidationFailedException;
use Celeris\Framework\Database\ActiveRecord\Resolver\DbalEntityManagerResolver;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Connection\InMemoryQueryTracer;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\Attribute\Column;
use Celeris\Framework\Database\ORM\Attribute\Entity;
use Celeris\Framework\Database\ORM\Attribute\Id;
use Celeris\Framework\Database\ORM\Attribute\LazyRelation;
use Celeris\Framework\Database\Testing\ArrayConnection;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;

#[Entity(table: 'ar_contacts')]
/**
 * Represents contact records using AR ergonomics over ORM metadata.
 */
final class ArContact extends ActiveRecordModel
{
   #[Id(generated: false)]
   #[Column('id')]
   private int $id;

   #[Column('first_name')]
   private string $firstName;

   #[Column('last_name')]
   private string $lastName;

   #[Column('phone')]
   private string $phone;

   #[Column('address')]
   private string $address;

   #[Column('age')]
   private int $age;

   /**
    * Route contact data to a custom CRM connection to validate model-level connection selection.
    *
    * @return ?string
    */
   public static function connectionName(): ?string
   {
      return 'crm';
   }
}

#[Entity(table: 'ar_validated_contacts')]
/**
 * Represents AR records with custom validation rules in addition to metadata constraints.
 */
final class ArValidatedContact extends ActiveRecordModel
{
   #[Id(generated: false)]
   #[Column('id')]
   private int $id;

   #[Column('first_name')]
   private string $firstName;

   #[Column('age')]
   private int $age;

   /**
    * @return array<int|string, callable>
    */
   public function validationRules(): array
   {
      return [
         'age' => static fn (mixed $value, self $model): bool|string => is_int($value) && $value >= 18
            ? true
            : 'Age must be 18 or older.',
      ];
   }

   /**
    * Keep this model on the CRM connection for validation checks.
    *
    * @return ?string
    */
   public static function connectionName(): ?string
   {
      return 'crm';
   }
}

#[Entity(table: 'ar_companies')]
/**
 * Represents company records in the default connection used by AR relation tests.
 */
final class ArCompany extends ActiveRecordModel
{
   #[Id(generated: false)]
   #[Column('id')]
   private int $id;

   #[Column('name')]
   private string $name;
}

#[Entity(table: 'ar_employees')]
/**
 * Represents employee records with a lazy relation to company.
 */
final class ArEmployee extends ActiveRecordModel
{
   #[Id(generated: false)]
   #[Column('id')]
   private int $id;

   #[Column('company_id')]
   private int $companyId;

   #[Column('name')]
   private string $name;

   #[LazyRelation(targetEntity: ArCompany::class, localKey: 'companyId', targetKey: 'id')]
   private mixed $company;
}

#[Entity(table: 'ar_provider_contacts')]
/**
 * Represents a lightweight model used to verify provider boot binding.
 */
final class ArProviderContact extends ActiveRecordModel
{
   #[Id(generated: false)]
   #[Column('id')]
   private int $id;
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
 * Build an isolated AR runtime fixture with default and CRM connections.
 *
 * @return array{manager: ActiveRecordManager, defaultTracer: InMemoryQueryTracer, crmTracer: InMemoryQueryTracer, resolver: DbalEntityManagerResolver}
 */
function buildArFixture(): array
{
   $defaultTracer = new InMemoryQueryTracer();
   $crmTracer = new InMemoryQueryTracer();

   $default = new ArrayConnection('default', $defaultTracer);
   $crm = new ArrayConnection('crm', $crmTracer);

   $default->execute('CREATE TABLE IF NOT EXISTS ar_companies (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
   $default->execute('CREATE TABLE IF NOT EXISTS ar_employees (id INTEGER PRIMARY KEY, company_id INTEGER NOT NULL, name TEXT NOT NULL)');

   $crm->execute('CREATE TABLE IF NOT EXISTS ar_contacts (id INTEGER PRIMARY KEY, first_name TEXT NOT NULL, last_name TEXT NOT NULL, phone TEXT NOT NULL, address TEXT NOT NULL, age INTEGER NOT NULL)');
   $crm->execute('CREATE TABLE IF NOT EXISTS ar_validated_contacts (id INTEGER PRIMARY KEY, first_name TEXT NOT NULL, age INTEGER NOT NULL)');

   $pool = new ConnectionPool();
   $pool->addConnection('default', $default);
   $pool->addConnection('crm', $crm);

   $dbal = new DBAL($pool);
   $resolver = new DbalEntityManagerResolver($dbal);
   $manager = new ActiveRecordManager($resolver);
   ActiveRecordModel::setManager($manager);

   return [
      'manager' => $manager,
      'defaultTracer' => $defaultTracer,
      'crmTracer' => $crmTracer,
      'resolver' => $resolver,
   ];
}

/**
 * Handle run crud and magic access tests.
 *
 * @return void
 */
function runCrudAndMagicAccessTests(): void
{
   $fixture = buildArFixture();

   $created = ArContact::create([
      'id' => 1,
      'firstName' => 'Ada',
      'lastName' => 'Lovelace',
      'phone' => '+1-555-0100',
      'address' => '10 Analytical Engine Ave',
      'age' => 36,
   ]);

   assertTrue($created->exists(), 'Saved AR model should be marked as persisted.');
   assertTrue($created->isDirty() === false, 'Saved AR model should clear dirty flags.');
   assertTrue($created->firstName === 'Ada', 'Magic getter should read private mapped properties.');

   $created->nickname = 'Countess';
   assertTrue($created->nickname === 'Countess', 'Magic setter/getter should support dynamic attributes.');
   assertTrue($created->isDirty(), 'Setting dynamic attributes should mark model as dirty.');

   $created->save();
   assertTrue($created->isDirty() === false, 'Saving should clear dirty flag for dynamic attribute updates.');

   $foundA = ArContact::findOrFail(1);
   $foundB = ArContact::findOrFail(1);
   assertTrue($foundA !== $foundB, 'Resolver should provide operation-local entity managers (no shared identity map across calls).');

   $foundA->phone = '+1-555-0199';
   $foundA->save();
   $reloaded = ArContact::findOrFail(1);
   assertTrue($reloaded->phone === '+1-555-0199', 'Save should persist updates from magic-mapped private properties.');

   $reloaded->delete();
   assertTrue(ArContact::find(1) === null, 'Delete should remove persisted row.');

   $crmEntries = count($fixture['crmTracer']->all());
   $defaultEntries = count($fixture['defaultTracer']->all());
   assertTrue($crmEntries > 0, 'Contact model should execute SQL against CRM connection.');
   assertTrue($defaultEntries === 2, 'Default connection should only contain schema setup queries in CRUD contact test.');
}

/**
 * Handle run validation tests.
 *
 * @return void
 */
function runValidationTests(): void
{
   buildArFixture();

   $invalid = new ArValidatedContact();
   $invalid->id = 2;
   $invalid->firstName = '';
   $invalid->age = 15;

   try {
      $invalid->save();
      throw new RuntimeException('Expected validation failure for invalid AR model.');
   } catch (ValidationFailedException $exception) {
      $errors = $exception->errors();
      assertTrue(isset($errors['firstName']), 'Validation should enforce metadata non-null/non-empty constraints.');
      assertTrue(isset($errors['age']), 'Validation should enforce custom rule callbacks.');
   }

   $valid = new ArValidatedContact();
   $valid->id = 3;
   $valid->firstName = 'Grace';
   $valid->age = 31;
   $valid->save();

   assertTrue($valid->exists(), 'Valid model should persist successfully after passing validation.');
}

/**
 * Handle run query and relation tests.
 *
 * @return void
 */
function runQueryAndRelationTests(): void
{
   $fixture = buildArFixture();

   ArCompany::create(['id' => 10, 'name' => 'Celeris Labs']);
   ArEmployee::create(['id' => 101, 'companyId' => 10, 'name' => 'Lin']);
   ArEmployee::create(['id' => 102, 'companyId' => 10, 'name' => 'Sam']);

   $employees = ArEmployee::query()
      ->where('companyId', 10)
      ->orderBy('id', 'DESC')
      ->get();

   assertTrue(count($employees) === 2, 'Query API should return all matching rows.');
   assertTrue($employees[0]->id === 102, 'Query ordering should be deterministic and descending by id.');

   $before = count($fixture['defaultTracer']->all());
   $employee = ArEmployee::findOrFail(101);
   $company = $employee->company;
   $after = count($fixture['defaultTracer']->all());

   assertTrue($company instanceof ArCompany, 'Magic relation access should lazy-load related model.');
   assertTrue($company->name === 'Celeris Labs', 'Lazy-loaded relation should resolve the correct related row.');
   assertTrue($after >= $before + 2, 'Finding model and loading lazy relation should execute explicit SQL queries.');

   $first = ArEmployee::query()->whereOp('id', '>=', 101)->orderBy('id', 'ASC')->first();
   assertTrue($first instanceof ArEmployee, 'Query::first should return a model instance when rows match.');
   assertTrue($first->id === 101, 'Query::first should respect deterministic ordering.');
}

/**
 * Handle run provider boot binding tests.
 *
 * @return void
 */
function runProviderBootBindingTests(): void
{
   ActiveRecordModel::clearManager();

   $pool = new ConnectionPool();
   $pool->addConnection('default', new ArrayConnection('default'));
   $dbal = new DBAL($pool);

   $services = new ServiceRegistry();
   $services->singleton(
      DBAL::class,
      static fn (ContainerInterface $container): DBAL => $dbal,
      [],
      true,
   );
   $services->singleton(
      DomainEventDispatcher::class,
      static fn (ContainerInterface $container): DomainEventDispatcher => new DomainEventDispatcher(),
      [],
      true,
   );

   $provider = new ActiveRecordServiceProvider();
   $provider->register($services);

   $container = new Container($services->all());
   $container->validateCircularDependencies();
   $provider->boot($container);

   $resolved = $container->get(ActiveRecordManager::class);
   assertTrue($resolved instanceof ActiveRecordManager, 'Provider should register ActiveRecordManager service.');

   $query = ArProviderContact::query();
   assertTrue($query instanceof ActiveRecordQuery, 'Provider boot should bind manager to ActiveRecordModel static facade.');
}

$checks = [
   'CrudAndMagicAccess' => 'runCrudAndMagicAccessTests',
   'Validation' => 'runValidationTests',
   'QueryAndRelations' => 'runQueryAndRelationTests',
   'ProviderBootBinding' => 'runProviderBootBindingTests',
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
