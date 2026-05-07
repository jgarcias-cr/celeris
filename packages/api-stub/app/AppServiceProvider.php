<?php

declare(strict_types=1);

namespace App;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Repositories\ContactRepository;
use App\Services\AuthService;
use App\Services\ContactService;
use App\Events\ContactCreatedEvent;
use App\Listeners\ContactCreatedListener;
use App\Listeners\Models\ContactLifecycleListener;

use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Events\ModelEventManager;
use Celeris\Framework\Security\Password\PasswordHasher;
use Celeris\Framework\Container\BootableServiceProviderInterface;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;
use Celeris\Framework\Logging\LoggerInterface;

/**
 * Registers core application services for the API stub.
 *
 * Use this provider as your main composition root to bind
 * repositories, services, and external integrations used by
 * API controllers.
 */
final class AppServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
   public function register(ServiceRegistry $services): void
   {
      $services->singleton(
         AuthService::class,
         static fn(ContainerInterface $c): AuthService => new AuthService(
            $c->get(ConfigRepository::class),
            $c->get(PasswordHasher::class),
         ),
         [ConfigRepository::class, PasswordHasher::class],
      );

      $services->singleton(
         ContactRepository::class,
         static fn(ContainerInterface $c): ContactRepository => new ContactRepository(),
      );

      $services->singleton(
         ModelEventManager::class,
         static fn(ContainerInterface $c): ModelEventManager => self::buildModelEvents(
            $c->get(LoggerInterface::class),
         ),
         [LoggerInterface::class],
      );

      $services->singleton(
         ContactService::class,
         static fn(ContainerInterface $c): ContactService => new ContactService(
            $c->get(ContactRepository::class),
            $c->get(ModelEventManager::class),
            $c->get(DomainEventDispatcher::class),
         ),
         [ContactRepository::class, ModelEventManager::class, DomainEventDispatcher::class],
      );

      $services->singleton(
         AuthController::class,
         static fn(ContainerInterface $c): AuthController => new AuthController(
            $c->get(AuthService::class),
         ),
         [AuthService::class],
      );

      $services->singleton(
         ContactController::class,
         static fn(ContainerInterface $c): ContactController => new ContactController(
            $c->get(ContactService::class),
         ),
         [ContactService::class],
      );

      $services->singleton(
         ContactCreatedListener::class,
         static fn(ContainerInterface $c): ContactCreatedListener => new ContactCreatedListener(
            $c->get(LoggerInterface::class),
         ),
         [LoggerInterface::class],
      );
   }


   /**
    * Boots the service provider by registering event listeners.
    * @param ContainerInterface $container The service container instance.
    * @return void
    */
   public function boot(ContainerInterface $container): void
   {
      $events = $container->get(DomainEventDispatcher::class);
      $events->listen(ContactCreatedEvent::class, $container->get(ContactCreatedListener::class));
   }


   /**
    * Builds the model event manager.
    *
    * @return ModelEventManager
    */
   private static function buildModelEvents(LoggerInterface $logger): ModelEventManager
   {
      ContactLifecycleListener::useLogger($logger);

      $events = new ModelEventManager();
      $events->autodiscover(dirname(__DIR__) . '/app/Listeners/Models', 'App\\Listeners\\Models');
      return $events;
   }
}
