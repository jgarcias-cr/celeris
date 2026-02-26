<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Base\HomePageControllerBase;
use Celeris\Framework\Routing\Attribute\RouteGroup;

/**
 * User-editable welcome page controller.
 *
 * Keep route-group metadata and custom endpoints/actions here.
 * Regeneration updates only `Controllers\Base\HomePageControllerBase`.
 */
#[RouteGroup(tags: ['Welcome UI'])]
final class HomePageController extends HomePageControllerBase
{
}
