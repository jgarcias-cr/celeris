<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: model the allowed http status values used by Http logic.
 * How: uses native enum cases to keep branching and serialization type-safe and explicit.
 * Used in framework: referenced by http logic, serialization, and guard conditions.
 */
enum HttpStatus: int
{
   case OK = 200;
   case CREATED = 201;
   case NO_CONTENT = 204;
   case BAD_REQUEST = 400;
   case UNAUTHORIZED = 401;
   case FORBIDDEN = 403;
   case NOT_FOUND = 404;
   case METHOD_NOT_ALLOWED = 405;
   case CONFLICT = 409;
   case UNPROCESSABLE_ENTITY = 422;
   case TOO_MANY_REQUESTS = 429;
   case INTERNAL_SERVER_ERROR = 500;
   case SERVICE_UNAVAILABLE = 503;

   /**
    * Handle reason phrase.
    *
    * @return string
    */
   public function reasonPhrase(): string
   {
      return match ($this) {
         self::OK => 'OK',
         self::CREATED => 'Created',
         self::NO_CONTENT => 'No Content',
         self::BAD_REQUEST => 'Bad Request',
         self::UNAUTHORIZED => 'Unauthorized',
         self::FORBIDDEN => 'Forbidden',
         self::NOT_FOUND => 'Not Found',
         self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
         self::CONFLICT => 'Conflict',
         self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
         self::TOO_MANY_REQUESTS => 'Too Many Requests',
         self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
         self::SERVICE_UNAVAILABLE => 'Service Unavailable',
      };
   }
}



