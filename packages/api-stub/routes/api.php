<?php

declare(strict_types=1);

use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\ContactController;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Routing\RouteMetadata;

/** @var Kernel $kernel */

$kernel->registerController(AuthController::class, new RouteGroup(prefix: '/api'));
$kernel->registerController(ContactController::class, new RouteGroup(prefix: '/api'));

$kernel->routes()->get('/',
   static function (ConfigRepository $config): Response {
      $frameworkVersion = 'unknown';
      $installedVersionsClass = 'Composer\\InstalledVersions';
      if (class_exists($installedVersionsClass) && $installedVersionsClass::isInstalled('celeris/framework')) {
         $frameworkVersion = $installedVersionsClass::getPrettyVersion('celeris/framework') ?? 'unknown';
      }

      $payload = [
         'name' => (string) $config->get('app.name', 'Celeris API'),
         'api_version' => (string) $config->get('app.version', '1.0.0'),
         'framework' => [
            'name' => 'celeris/framework',
            'version' => $frameworkVersion,
         ],
         'endpoints' => [
            'auth' => '/api/auth',
            'contacts' => '/api/contacts',
            'health' => '/health',
         ],
      ];

      return new Response(
         200,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
      );
   },
   [],
   new RouteMetadata(name: 'api.info', summary: 'API info', tags: ['API']),
);
