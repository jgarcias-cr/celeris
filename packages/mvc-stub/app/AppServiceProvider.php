<?php

declare(strict_types=1);

namespace App;

use App\Repositories\ContactRepository;
use App\Services\ContactService;
use App\Shared\ViewRenderer;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;

final class AppServiceProvider implements ServiceProviderInterface
{
   public function register(ServiceRegistry $services): void
   {
      $services->singleton(
         ViewRenderer::class,
         static fn(ContainerInterface $c): ViewRenderer => new ViewRenderer(__DIR__ . '/Views'),
      );

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
