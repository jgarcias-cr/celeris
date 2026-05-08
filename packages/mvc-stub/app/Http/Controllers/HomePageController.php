<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\View\TemplateRendererInterface;

/**
 * User-editable welcome page controller.
 */
#[RouteGroup(tags: ['Welcome UI'])]
final class HomePageController
{
   public function __construct(
      protected TemplateRendererInterface $views,
   ) {}

   #[Route(methods: ['GET'], path: '/', summary: 'Welcome page')]
   public function index(): Response
   {
      $content = $this->views->render('welcome');

      $html = $this->views->render('layout', [
         'title' => 'Welcome to Celeris',
         'content' => $content,
         'username' => 'Guest',
      ]);

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }
}
