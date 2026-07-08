<?php

namespace App\Helpers;

class CustomResponse
{
    /**
     * Build a JSON success envelope (default HTTP 200) carrying the message and operation label.
     *
     * @param  string  $message
     * @param  string  $operation
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public static function ok(
        $message = 'Operation successful',
        $operation = null,
        $statusCode = 200
    ) {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'operation' => $operation,
        ], $statusCode);
    }

    /**
     * Build a JSON success envelope for a created resource (default HTTP 201 Created).
     *
     * @param  string  $message
     * @param  string  $operation
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public static function created(
        $message = 'Operation successful',
        $operation = null,
        $statusCode = 201
    ) {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'operation' => $operation,
        ], $statusCode);
    }
    /**
     * Build a minimal JSON success envelope (default HTTP 200) with only status and message.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success(
        $message = 'Operation successful',
        $statusCode = 200
    ) {
        return response()->json([
            'status' => 'success',
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Build a JSON error envelope (default HTTP 500) with an error status, message, and optional error details.
     *
     * @param  mixed $message
     * @param  int  $statusCode
     * @param  mixed  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error($message = 'Operation failed', $statusCode = 500, $errors = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Log the exception and return a generic error response with a correlation ID.
     * Use this instead of exposing $e->getMessage() to clients.
     */
    public static function serverError(\Throwable $e, string $context = '', array $logExtra = [])
    {
        $correlationId = uniqid('err_', true);
        \Illuminate\Support\Facades\Log::error("[$context] Unexpected server error", array_merge([
            'correlation_id' => $correlationId,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], $logExtra));

        return self::error('An unexpected error occurred. Reference: ' . $correlationId, 500);
    }
}
