<?php

declare(strict_types=1);

return [
   // Limit default CORS handling to API-style endpoints. Add page paths only if you need them.
   'paths' => ['/api/*'],
   // Update these origins to match the browser apps allowed to call this application cross-origin.
   'allowed_origins' => ['http://localhost:3000'],
   'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
   'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Version', 'X-Csrf-Token'],
   'exposed_headers' => [],
   'supports_credentials' => false,
   'max_age' => 600,
];
