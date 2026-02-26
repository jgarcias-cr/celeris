<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Base\ContactPageControllerBase;
use Celeris\Framework\Routing\Attribute\RouteGroup;

/**
 * User-editable contacts page controller.
 *
 * Keep route-group metadata and custom endpoints/actions here.
 * Regeneration updates only `Controllers\Base\ContactPageControllerBase`.
 */
#[RouteGroup(prefix: '/contacts', tags: ['Contacts UI'])]
final class ContactPageController extends ContactPageControllerBase
{
}
