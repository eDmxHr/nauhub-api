<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(
        string $message = 'Validation failed',
        $errors = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 422,
            errorCode: 'VALIDATION_ERROR',
            details: ['errors' => $errors],
            previous: $previous
        );
    }
}
