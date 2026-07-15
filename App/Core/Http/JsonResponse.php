<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * JsonResponse — standardised JSON API responses.
 *
 * Success shape:  {"data": ...}
 * Error shape:    {"error": {"message": "...", "status": N, "details": {...}}}
 *
 * The *Body() methods return arrays and are safe to call from tests.
 * The action methods (success, error, …) set headers and exit.
 *
 * Usage:
 *   JsonResponse::success($user, 201);
 *   JsonResponse::notFound('User not found');
 *   JsonResponse::validationError($formRequest->errors());
 */
class JsonResponse
{
    // -----------------------------------------------------------------------
    // Payload builders (testable, no side-effects)
    // -----------------------------------------------------------------------

    public static function successBody(mixed $data): array
    {
        return ['data' => $data];
    }

    public static function errorBody(string $message, int $status, array $details = []): array
    {
        $error = ['message' => $message, 'status' => $status];
        if (!empty($details)) {
            $error['details'] = $details;
        }
        return ['error' => $error];
    }

    public static function validationErrorBody(array $errors, string $message = 'Validation failed'): array
    {
        return ['error' => ['message' => $message, 'status' => 422, 'errors' => $errors]];
    }

    // -----------------------------------------------------------------------
    // Response senders
    // -----------------------------------------------------------------------

    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** 200 OK with data envelope. */
    public static function success(mixed $data, int $status = 200): never
    {
        self::send(self::successBody($data), $status);
    }

    /** 201 Created. */
    public static function created(mixed $data): never
    {
        self::send(self::successBody($data), 201);
    }

    /** 204 No Content. */
    public static function noContent(): never
    {
        http_response_code(204);
        header('Content-Type: application/json; charset=utf-8');
        exit;
    }

    /** Generic error with an explicit HTTP status. */
    public static function error(string $message, int $status = 400, array $details = []): never
    {
        self::send(self::errorBody($message, $status, $details), $status);
    }

    /** 404 Not Found. */
    public static function notFound(string $message = 'Not found'): never
    {
        self::send(self::errorBody($message, 404), 404);
    }

    /** 401 Unauthorized. */
    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::send(self::errorBody($message, 401), 401);
    }

    /** 403 Forbidden. */
    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::send(self::errorBody($message, 403), 403);
    }

    /** 422 Unprocessable Entity — field-level validation errors. */
    public static function validationError(array $errors, string $message = 'Validation failed'): never
    {
        self::send(self::validationErrorBody($errors, $message), 422);
    }
}
