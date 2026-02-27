<?php

declare(strict_types=1);

namespace App;

use App\Http\Controllers\ContactPageController;
use App\Http\Controllers\HomePageController;
use App\Repositories\ContactRepository;
use App\Services\ContactService;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\View\TemplateRendererFactory;
use Celeris\Framework\View\TemplateRendererInterface;

/**
 * Registers application services for the MVC stub.
 *
 * This is the main place to wire container bindings for your project.
 * In real applications you typically:
 * - bind repositories to database-backed implementations;
 * - register additional domain/application services;
 * - expose optional third-party integrations required by your modules.
 */
final class AppServiceProvider implements ServiceProviderInterface
{
   public function register(ServiceRegistry $services): void
   {
      $services->singleton(
         TemplateRendererInterface::class,
         static fn (ContainerInterface $c): TemplateRendererInterface => self::buildRenderer($c),
         [ConfigRepository::class],
      );

      $services->singleton(
         'view.renderer',
         static fn (ContainerInterface $c): TemplateRendererInterface => $c->get(TemplateRendererInterface::class),
         [TemplateRendererInterface::class],
      );

      $services->singleton(
         ContactRepository::class,
         static fn (ContainerInterface $c): ContactRepository => new ContactRepository(
            $c->has(DBAL::class) ? $c->get(DBAL::class) : null,
         ),
      );

      $services->singleton(
         ContactService::class,
         static fn (ContainerInterface $c): ContactService => new ContactService($c->get(ContactRepository::class)),
         [ContactRepository::class],
      );

      $services->singleton(
         HomePageController::class,
         static fn (ContainerInterface $c): HomePageController => new HomePageController(
            $c->get(TemplateRendererInterface::class),
         ),
         [TemplateRendererInterface::class],
      );

      $services->singleton(
         ContactPageController::class,
         static fn (ContainerInterface $c): ContactPageController => new ContactPageController(
            $c->get(ContactService::class),
            $c->get(TemplateRendererInterface::class),
         ),
         [ContactService::class, TemplateRendererInterface::class],
      );
   }

   private static function buildRenderer(ContainerInterface $container): TemplateRendererInterface
   {
      $config = [];
      if ($container->has(ConfigRepository::class)) {
         $repository = $container->get(ConfigRepository::class);
         if ($repository instanceof ConfigRepository) {
            $raw = $repository->get('app.view', []);
            if (is_array($raw)) {
               $config = $raw;
            }
         }
      }

      $twigEnvironment = self::optionalDependency($container, 'Twig\\Environment');
      $platesEngine = self::optionalDependency($container, 'League\\Plates\\Engine');
      $latteEngine = self::optionalDependency($container, 'Latte\\Engine');

      return TemplateRendererFactory::fromConfig(
         $config,
         $twigEnvironment,
         $platesEngine,
         $latteEngine,
      );
   }

   private static function optionalDependency(ContainerInterface $container, string $id): ?object
   {
      if (!$container->has($id)) {
         return null;
      }

      $dependency = $container->get($id);
      return is_object($dependency) ? $dependency : null;
   }
}
