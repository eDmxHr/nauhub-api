<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(
        string $message = 'Access forbidden',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 403,
            errorCode: 'FORBIDDEN',
            details: null,
            previous: $previous
        );
    }
}
