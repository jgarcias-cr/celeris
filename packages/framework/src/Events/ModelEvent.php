<?php

declare(strict_types=1);

namespace Celeris\Framework\Events;

/**
 * Represents an application-level model lifecycle event.
 */
final class ModelEvent
{
   public const CREATE = 'onCreate';
   public const UPDATE = 'onUpdate';
   public const DELETE = 'onDelete';
   public const SHOW = 'onShow';

   /**
    * Creates a new ModelEvent instance.
    *
    * @param string $name The name of the model event (e.g., 'onCreate', 'onUpdate').
    * @param object $model The model instance associated with the event.
    * @param array<string, mixed> $context
    */
   public function __construct(private string $name, private object $model, private array $context = []) {
      $this->name = trim($this->name);
      if ($this->name === '') {
         throw new \InvalidArgumentException('Model event name cannot be empty.');
      }
   }


   /**
    * Returns the name of the model event.
    *
    * @return string The name of the model event.
    */
   public function name(): string
   {
      return $this->name;
   }


   /**
    * Returns the model instance associated with the event.
    *
    * @return object The model instance.
    */
   public function model(): object
   {
      return $this->model;
   }


   /**
    * Returns the class name of the model associated with the event.
    *
    * @return string The class name of the model.
    */
   public function modelClass(): string
   {
      return $this->model::class;
   }


   /**
    * Returns the context associated with the event.
    *
    * @return array<string, mixed>
    */
   public function context(): array
   {
      return $this->context;
   }
}
