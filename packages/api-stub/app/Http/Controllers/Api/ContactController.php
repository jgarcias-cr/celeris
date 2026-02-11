<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\DTOs\CreateContactDto;
use App\Http\DTOs\UpdateContactDto;
use App\Services\ContactService;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;

#[RouteGroup(prefix: '/contacts', version: 'v1', tags: ['Contacts'])]
final class ContactController
{
   public function __construct(private ContactService $service) {}

   #[Route(methods: ['GET'], path: '/', summary: 'List contacts')]
   public function index(): array
   {
      return $this->service->list();
   }


   #[Route(methods: ['GET'], path: '/{id}', summary: 'Get contact')]
   public function show(int $id): array
   {
      return $this->service->getOrFail($id)->toArray();
   }


   #[Route(methods: ['POST'], path: '/', summary: 'Create contact')]
   public function create(Request $request): Response
   {
      $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];

      $dto = new CreateContactDto(
         id: (int) ($data['id'] ?? 0),
         firstName: (string) ($data['first_name'] ?? ''),
         lastName: (string) ($data['last_name'] ?? ''),
         phone: (string) ($data['phone'] ?? ''),
         address: (string) ($data['address'] ?? ''),
         age: (int) ($data['age'] ?? 0),
      );

      $contact = $this->service->create($dto);

      return new Response(
         201,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode(['id' => $contact->id]),
      );
   }


   #[Route(methods: ['PUT'], path: '/{id}', summary: 'Update contact')]
   public function update(int $id, Request $request): array
   {
      $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];

      $dto = new UpdateContactDto(
         firstName: isset($data['first_name']) ? (string) $data['first_name'] : null,
         lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
         phone: isset($data['phone']) ? (string) $data['phone'] : null,
         address: isset($data['address']) ? (string) $data['address'] : null,
         age: isset($data['age']) ? (int) $data['age'] : null,
      );

      return $this->service->update($id, $dto)->toArray();
   }

   
   #[Route(methods: ['DELETE'], path: '/{id}', summary: 'Delete contact')]
   public function delete(int $id): Response
   {
      $this->service->remove($id);
      return new Response(204);
   }
}
