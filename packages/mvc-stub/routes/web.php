<?php

declare(strict_types=1);

use App\Http\Controllers\ContactPageController;
use App\Http\Controllers\HomePageController;
use Celeris\Framework\Kernel\Kernel;

/** @var Kernel $kernel */

$kernel->registerController(ContactPageController::class);
$kernel->registerController(HomePageController::class);
