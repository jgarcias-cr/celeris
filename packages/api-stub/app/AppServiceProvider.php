<?php

declare(strict_types=1);

namespace App;

use App\Repositories\ContactRepository;
use App\Services\ContactService;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;

/**
 * Registers core application services for the API stub.
 *
 * Use this provider as your main composition root to bind
 * repositories, services, and external integrations used by
 * API controllers.
 */
final class AppServiceProvider implements ServiceProviderInterface
{
   public function register(ServiceRegistry $services): void
   {
      $services->singleton(
         ContactRepository::class,
         static fn(ContainerInterface $c): ContactRepository => new ContactRepository(),
      );

      $services->singleton(
         ContactService::class,
         static fn(ContainerInterface $c): ContactService => new ContactService($c->get(ContactRepository::class)),
         [ContactRepository::class],
      );
   }
}
