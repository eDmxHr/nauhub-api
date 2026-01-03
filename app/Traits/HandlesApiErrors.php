<?php

namespace App\Traits;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;

trait HandlesApiErrors
{
    /**
     * Throw a not found exception
     */
    protected function notFound(string $message = 'Resource not found', ?string $resource = null): never
    {
        throw new NotFoundException($message, $resource);
    }

    /**
     * Throw an unauthorized exception
     */
    protected function unauthorized(string $message = 'Unauthorized'): never
    {
        throw new UnauthorizedException($message);
    }

    /**
     * Throw a forbidden exception
     */
    protected function forbidden(string $message = 'Access forbidden'): never
    {
        throw new ForbiddenException($message);
    }

    /**
     * Throw a bad request exception
     */
    protected function badRequest(string $message = 'Bad request', $details = null): never
    {
        throw new BadRequestException($message, $details);
    }

    /**
     * Throw a conflict exception
     */
    protected function conflict(string $message = 'Resource conflict', $details = null): never
    {
        throw new ConflictException($message, $details);
    }

    /**
     * Throw a validation exception
     */
    protected function validationError(string $message = 'Validation failed', $errors = null): never
    {
        throw new ValidationException($message, $errors);
    }
}
