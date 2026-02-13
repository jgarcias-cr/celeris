<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Base\ContactControllerBase;
use Celeris\Framework\Routing\Attribute\RouteGroup;

/**
 * User-editable contacts API controller.
 *
 * Keep route-group level metadata and custom endpoints here.
 * Regeneration updates only `Api\\Base\\ContactControllerBase`.
 */
#[RouteGroup(prefix: '/contacts', version: 'v1', tags: ['Contacts'])]
final class ContactController extends ContactControllerBase
{
}
