<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(
        string $message = 'Unauthorized',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 401,
            errorCode: 'UNAUTHORIZED',
            details: null,
            previous: $previous
        );
    }
}
