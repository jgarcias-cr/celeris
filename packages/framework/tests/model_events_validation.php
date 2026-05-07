<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Events\ModelEvent;
use Celeris\Framework\Events\ModelEventListenerInterface;
use Celeris\Framework\Events\ModelEventManager;
use Celeris\Framework\Events\ModelEventSubscriberInterface;

spl_autoload_register(static function (string $class): void {
   $prefix = 'CelerisModelEventTest\\';
   if (!str_starts_with($class, $prefix)) {
      return;
   }

   $relative = substr($class, strlen($prefix));
   $file = sys_get_temp_dir() . '/celeris_model_event_listeners/' . strtr($relative, '\\', '/') . '.php';
   if (is_file($file)) {
      require $file;
   }
});

final class ModelEventTestContact
{
   public function __construct(public int $id) {}
}

final class RecordingModelListener implements ModelEventListenerInterface
{
   /** @var array<int, string> */
   public array $events = [];

   public function handle(ModelEvent $event): void
   {
      $this->events[] = $event->name() . ':' . $event->modelClass();
   }
}

function assertTrue(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

$manager = new ModelEventManager();
$listener = new RecordingModelListener();
$model = new ModelEventTestContact(42);

$manager->listen(ModelEvent::CREATE, $listener, ModelEventTestContact::class);
$manager->listen(ModelEvent::SHOW, $listener);
$manager->onCreate($model);
$manager->onUpdate($model);
$manager->onShow($model);

assertTrue(
   $listener->events === [
      ModelEvent::CREATE . ':' . ModelEventTestContact::class,
      ModelEvent::SHOW . ':' . ModelEventTestContact::class,
   ],
   'Manual model event listeners should resolve by event name and model class.'
);

$listenerDir = sys_get_temp_dir() . '/celeris_model_event_listeners/Listeners/Models';
if (!is_dir($listenerDir) && !mkdir($listenerDir, 0777, true) && !is_dir($listenerDir)) {
   throw new RuntimeException('Could not create temporary listener directory.');
}

file_put_contents(
   $listenerDir . '/DiscoveredContactListener.php',
   <<<'PHP'
<?php

declare(strict_types=1);

namespace CelerisModelEventTest\Listeners\Models;

use Celeris\Framework\Events\ModelEvent;
use Celeris\Framework\Events\ModelEventSubscriberInterface;
use ModelEventTestContact;

final class DiscoveredContactListener implements ModelEventSubscriberInterface
{
   public static array $events = [];

   public static function subscribedEvents(): array
   {
      return [ModelEvent::DELETE];
   }

   public static function subscribedModels(): array
   {
      return [ModelEventTestContact::class];
   }

   public function handle(ModelEvent $event): void
   {
      self::$events[] = $event->name() . ':' . $event->model()->id;
   }
}
PHP
);

$manager = new ModelEventManager();
$manager->autodiscover($listenerDir, 'CelerisModelEventTest\\Listeners\\Models');
$manager->onCreate($model);
$manager->onDelete($model);

assertTrue(
   \CelerisModelEventTest\Listeners\Models\DiscoveredContactListener::$events === [ModelEvent::DELETE . ':42'],
   'Autodiscovered model event subscribers should filter by subscribed event and model.'
);

echo "model_events_validation: ok\n";
