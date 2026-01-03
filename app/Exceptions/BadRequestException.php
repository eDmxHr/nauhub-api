<?php

namespace App\Exceptions;

class BadRequestException extends ApiException
{
    public function __construct(
        string $message = 'Bad request',
        $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 400,
            errorCode: 'BAD_REQUEST',
            details: $details,
            previous: $previous
        );
    }
}
