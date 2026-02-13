<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ContactService;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\View\TemplateRendererInterface;

/**
 * Handles server-rendered contact pages for the MVC example.
 *
 * The controller demonstrates a simple and reusable pattern:
 * 1. Render a page fragment (`contacts/*` templates).
 * 2. Wrap the fragment in the shared `layout` template.
 *
 * Extend this class by adding new route methods and passing the
 * required data to view fragments.
 */
#[RouteGroup(prefix: '/contacts', version: 'v1', tags: ['Contacts UI'])]
final class ContactPageController
{
   public function __construct(
      private ContactService $service,
      private TemplateRendererInterface $views,
   ) {}

   #[Route(methods: ['GET'], path: '/', summary: 'Contacts page')]
   public function index(): Response
   {
      $html = $this->renderPage('Contacts', 'contacts/index', [
         'contacts' => $this->service->list(),
      ]);

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }

   #[Route(methods: ['GET'], path: '/{id}', summary: 'Contact details page')]
   public function show(int $id): Response
   {
      $html = $this->renderPage('Contact', 'contacts/show', [
         'contact' => $this->service->getOrFail($id),
      ]);

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }

   /**
    * @param array<string, mixed> $data
    */
   private function renderPage(string $title, string $template, array $data = []): string
   {
      $content = $this->views->render($template, $data);

      return $this->views->render('layout', [
         'title' => $title,
         'content' => $content,
         'username' => $data['username'] ?? 'Guest',
      ]);
   }
}
