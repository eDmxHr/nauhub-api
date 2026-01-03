<?php

namespace App\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(
        string $message = 'Resource conflict',
        $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 409,
            errorCode: 'CONFLICT',
            details: $details,
            previous: $previous
        );
    }
}
