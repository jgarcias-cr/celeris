<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ContactService;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\View\TemplateRendererInterface;

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
      $html = $this->views->render('contacts/index', [
         'title' => 'Contacts',
         'contacts' => $this->service->list(),
      ]);

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }

   #[Route(methods: ['GET'], path: '/{id}', summary: 'Contact details page')]
   public function show(int $id): Response
   {
      $html = $this->views->render('contacts/show', [
         'title' => 'Contact',
         'contact' => $this->service->getOrFail($id),
      ]);

      return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
   }
}
