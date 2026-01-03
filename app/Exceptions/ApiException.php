<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    protected $statusCode;
    protected $errorCode;
    protected $details;

    public function __construct(
        string $message = 'An error occurred',
        int $statusCode = 500,
        ?string $errorCode = null,
        $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode ?? 'ERROR_' . $statusCode;
        $this->details = $details;
    }

    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->message,
            'error_code' => $this->errorCode,
        ];

        if ($this->details !== null) {
            $response['details'] = $this->details;
        }

        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($this),
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => $this->getTrace(),
            ];
        }

        return response()->json($response, $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails()
    {
        return $this->details;
    }
}
