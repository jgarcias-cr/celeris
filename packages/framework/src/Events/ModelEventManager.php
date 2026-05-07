<?php

declare(strict_types=1);

namespace Celeris\Framework\Events;

use ReflectionClass;

/**
 * Dispatches application-level model events and discovers app listeners.
 */
final class ModelEventManager
{
   /** @var array<string, array<int, ModelEventListenerInterface>> */
   private array $listeners = [];

   /** @var array<int, string> */
   private array $discoveredPaths = [];


   /**
    * Registers a listener for a specific model event.
    *
    * @param string $eventName The name of the model event to listen for.
    * @param ModelEventListenerInterface $listener The listener to register.
    * @param string $modelClass The class name of the model to listen for.
    * @return void
    */
   public function listen(string $eventName, ModelEventListenerInterface $listener, string $modelClass = '*'): void
   {
      $eventName = trim($eventName);
      $modelClass = trim($modelClass);

      if ($eventName === '') {
         throw new \InvalidArgumentException('Model event name cannot be empty.');
      }

      if ($modelClass === '') {
         $modelClass = '*';
      }

      $this->listeners[$this->listenerKey($eventName, $modelClass)][] = $listener;
   }


   /**
    * Dispatches a model event.
    *
    * @param string $eventName The name of the model event to dispatch.
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context The context for the event.
    * @return void
    */
   public function dispatch(string $eventName, object $model, array $context = []): void
   {
      $event = new ModelEvent($eventName, $model, $context);

      foreach ($this->resolveListeners($event) as $listener) {
         $listener->handle($event);
      }
   }


   /**
    * Dispatches an onCreate event.
    *
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context The context for the event.
    * @return void
    */
   public function onCreate(object $model, array $context = []): void
   {
      $this->dispatch(ModelEvent::CREATE, $model, $context);
   }


   /**
    * Dispatches an onUpdate event.
    *
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context The context for the event.
    * @return void
    */
   public function onUpdate(object $model, array $context = []): void
   {
      $this->dispatch(ModelEvent::UPDATE, $model, $context);
   }


   /**
    * Dispatches an onDelete event.
    *
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context The context for the event.
    * @return void
    */
   public function onDelete(object $model, array $context = []): void
   {
      $this->dispatch(ModelEvent::DELETE, $model, $context);
   }


   /**
    * Dispatches an onShow event.
    *
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context The context for the event.
    * @return void
    */
   public function onShow(object $model, array $context = []): void
   {
      $this->dispatch(ModelEvent::SHOW, $model, $context);
   }


   /**
    * Discovers and registers model event listeners in the specified directory.
    *
    * @param string $directory The directory to search for listeners.
    * @param string $namespace The namespace for the discovered classes.
    * @return void
    */
   public function autodiscover(string $directory, string $namespace): void
   {
      $directory = rtrim($directory, DIRECTORY_SEPARATOR);
      $namespace = trim($namespace, '\\');

      if ($directory === '' || $namespace === '' || !is_dir($directory)) {
         return;
      }

      $realPath = realpath($directory) ?: $directory;
      if (in_array($realPath, $this->discoveredPaths, true)) {
         return;
      }

      $this->discoveredPaths[] = $realPath;

      $iterator = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
      );

      foreach ($iterator as $file) {
         if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
            continue;
         }

         $relative = substr($file->getPathname(), strlen($directory) + 1);
         $class = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relative);
         $this->registerDiscoveredClass($class);
      }
   }


   /**
    * Resolves the listeners for a given model event.
    *
    * @param ModelEvent $event The model event for which to resolve listeners.
    * @return array<int, ModelEventListenerInterface>
    */
   private function resolveListeners(ModelEvent $event): array
   {
      $modelClass = $event->modelClass();

      return [
         ...($this->listeners[$this->listenerKey('*', '*')] ?? []),
         ...($this->listeners[$this->listenerKey($event->name(), '*')] ?? []),
         ...($this->listeners[$this->listenerKey('*', $modelClass)] ?? []),
         ...($this->listeners[$this->listenerKey($event->name(), $modelClass)] ?? []),
      ];
   }


   /**
    * Registers a discovered class as a model event listener.
    *
    * @param string $class The class name to register.
    * @return void
    */
   private function registerDiscoveredClass(string $class): void
   {
      if (!class_exists($class) || !is_subclass_of($class, ModelEventListenerInterface::class)) {
         return;
      }

      $reflection = new ReflectionClass($class);
      if (!$reflection->isInstantiable() || $reflection->getConstructor()?->getNumberOfRequiredParameters() > 0) {
         return;
      }

      /** @var ModelEventListenerInterface $listener */
      $listener = $reflection->newInstance();
      $events = ['*'];
      $models = ['*'];

      if (is_subclass_of($class, ModelEventSubscriberInterface::class)) {
         $events = $this->normalizeSubscriptions($class::subscribedEvents());
         $models = $this->normalizeSubscriptions($class::subscribedModels());
      }

      foreach ($events as $eventName) {
         foreach ($models as $modelClass) {
            $this->listen($eventName, $listener, $modelClass);
         }
      }
   }


   /**
    * Normalizes subscription arrays by trimming values and removing empty entries.
    * If the resulting array is empty, it defaults to ['*'].
    *
    * @param array<int, string> $subscriptions
    * @return array<int, string>
    */
   private function normalizeSubscriptions(array $subscriptions): array
   {
      $normalized = array_values(array_filter(array_map(
         static fn(string $subscription): string => trim($subscription),
         $subscriptions,
      )));

      return $normalized === [] ? ['*'] : $normalized;
   }


   /**
    * Generates a unique key for storing listeners based on event name and model class.
    *
    * @param string $eventName The name of the model event.
    * @param string $modelClass The class name of the model.
    * @return string The generated listener key.
    */
   private function listenerKey(string $eventName, string $modelClass): string
   {
      return $eventName . '|' . $modelClass;
   }
}
