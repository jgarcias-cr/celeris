<?php

declare(strict_types=1);

return [
    // Match only API routes by default so page/tooling routes stay same-origin unless you opt in.
    'paths' => ['/api/*'],
    // Update these origins to match the browser apps allowed to call this API.
    'allowed_origins' => ['http://localhost:3000'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Version', 'X-Csrf-Token'],
    'exposed_headers' => [],
    'supports_credentials' => false,
    'max_age' => 600,
];
