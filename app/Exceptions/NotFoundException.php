<?php

namespace App\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Resource not found',
        ?string $resource = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 404,
            errorCode: 'NOT_FOUND',
            details: $resource ? ['resource' => $resource] : null,
            previous: $previous
        );
    }
}
