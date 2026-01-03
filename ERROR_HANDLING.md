# Centralized Error Handling

This application uses a centralized error handling system that provides consistent error responses and automatic logging.

## Overview

All API errors are automatically caught and formatted into a consistent JSON response structure:

```json
{
  "success": false,
  "message": "Error message here",
  "error_code": "ERROR_CODE",
  "details": {
    // Optional additional details
  },
  "debug": {
    // Only included when APP_DEBUG=true
    "exception": "ExceptionClassName",
    "file": "/path/to/file.php",
    "line": 123,
    "trace": []
  }
}
```

## Features

1. **Automatic Error Formatting**: All exceptions are converted to consistent JSON responses
2. **Error Logging**: Critical errors (5xx) are automatically logged to activity logs
3. **Debug Mode**: Detailed error information in development mode
4. **Custom Exceptions**: Pre-built exception classes for common scenarios

## Custom Exception Classes

### ApiException (Base Class)

Base exception for all custom API exceptions.

```php
use App\Exceptions\ApiException;

throw new ApiException(
    message: 'Something went wrong',
    statusCode: 500,
    errorCode: 'CUSTOM_ERROR',
    details: ['key' => 'value']
);
```

### BadRequestException (400)

For invalid requests.

```php
use App\Exceptions\BadRequestException;

throw new BadRequestException(
    'Invalid input provided',
    ['field' => 'value is invalid']
);
```

### UnauthorizedException (401)

For authentication failures.

```php
use App\Exceptions\UnauthorizedException;

throw new UnauthorizedException('Invalid credentials');
```

### ForbiddenException (403)

For authorization failures.

```php
use App\Exceptions\ForbiddenException;

throw new ForbiddenException('You do not have permission to access this resource');
```

### NotFoundException (404)

For missing resources.

```php
use App\Exceptions\NotFoundException;

throw new NotFoundException('User not found', 'User');
```

### ConflictException (409)

For resource conflicts (e.g., duplicate entries).

```php
use App\Exceptions\ConflictException;

throw new ConflictException(
    'Email already exists',
    ['email' => 'user@example.com']
);
```

### ValidationException (422)

For validation errors.

```php
use App\Exceptions\ValidationException;

throw new ValidationException(
    'Validation failed',
    ['email' => ['The email field is required']]
);
```

## Using the HandlesApiErrors Trait

For convenience, use the `HandlesApiErrors` trait in your controllers:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\HandlesApiErrors;

class MyController extends Controller
{
    use HandlesApiErrors;

    public function show($id)
    {
        $item = Item::find($id);

        if (!$item) {
            $this->notFound('Item not found', 'Item');
        }

        // Or use other helper methods:
        // $this->unauthorized('Not authenticated');
        // $this->forbidden('Access denied');
        // $this->badRequest('Invalid data');
        // $this->conflict('Duplicate entry');
        // $this->validationError('Invalid input', $errors);

        return response()->json(['item' => $item]);
    }
}
```

## Automatic Error Handling

The following exceptions are automatically handled:

- **ModelNotFoundException** → 404 with "Resource not found"
- **NotFoundHttpException** → 404 with "Endpoint not found"
- **MethodNotAllowedHttpException** → 405 with "Method not allowed"
- **AuthenticationException** → 401 with "Unauthenticated"
- **ValidationException** → 422 with validation errors
- **HttpException** → Uses exception's status code
- **All other exceptions** → 500 with generic message

## Error Logging

Critical errors (HTTP 500+) are automatically logged to the `activity_logs` table with:

- Action: `system_error`
- Severity: `critical`
- Metadata: Exception details, file, line, URL, HTTP method

View critical errors:

```
GET /api/admin/activity-logs/critical
GET /api/admin/activity-logs?severity=critical
GET /api/admin/activity-logs?action=system_error
```

## Examples

### Example 1: Handle Missing Resource

```php
public function show($id)
{
    $video = Video::find($id);

    if (!$video) {
        throw new NotFoundException("Video with ID {$id} not found", 'Video');
    }

    return response()->json(['video' => $video]);
}
```

Response (404):
```json
{
  "success": false,
  "message": "Video with ID 123 not found",
  "error_code": "NOT_FOUND",
  "details": {
    "resource": "Video"
  }
}
```

### Example 2: Handle Duplicate Entry

```php
public function store(Request $request)
{
    $existingUser = User::where('email', $request->email)->first();

    if ($existingUser) {
        throw new ConflictException(
            'A user with this email already exists',
            ['email' => $request->email]
        );
    }

    $user = User::create($request->all());
    return response()->json(['user' => $user], 201);
}
```

Response (409):
```json
{
  "success": false,
  "message": "A user with this email already exists",
  "error_code": "CONFLICT",
  "details": {
    "email": "user@example.com"
  }
}
```

### Example 3: Handle Authorization

```php
public function delete($id)
{
    $video = Video::findOrFail($id);

    if ($video->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
        throw new ForbiddenException('You can only delete your own videos');
    }

    $video->delete();
    return response()->json(['message' => 'Video deleted']);
}
```

Response (403):
```json
{
  "success": false,
  "message": "You can only delete your own videos",
  "error_code": "FORBIDDEN"
}
```

## Best Practices

1. **Use Specific Exceptions**: Use the most specific exception for the scenario
2. **Provide Clear Messages**: Write user-friendly error messages
3. **Include Details**: Add relevant details to help debugging
4. **Don't Expose Sensitive Data**: Never include passwords, tokens, or internal system details in error messages
5. **Use Activity Logs**: Critical errors are automatically logged, but you can also manually log important events

## Testing Error Handling

Test different error scenarios:

```bash
# Test 404 - Not Found
GET /api/admin/videos/999999

# Test 401 - Unauthenticated
GET /api/admin/videos
# (without Authorization header)

# Test 422 - Validation Error
POST /api/auth/login
{
  "email": "invalid-email"
}

# Test 405 - Method Not Allowed
DELETE /api/auth/login
```

## Debug Mode

When `APP_DEBUG=true` in `.env`, error responses include detailed debug information:

```json
{
  "success": false,
  "message": "An error occurred",
  "error_code": "INTERNAL_SERVER_ERROR",
  "debug": {
    "exception": "Exception",
    "file": "/path/to/file.php",
    "line": 42,
    "trace": [...]
  }
}
```

**Warning**: Always set `APP_DEBUG=false` in production to avoid exposing sensitive information!
