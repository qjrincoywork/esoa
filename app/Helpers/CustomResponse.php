<?php

namespace App\Helpers;

class CustomResponse
{
    /**
     * Success response.
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
     * Success response.
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
     * Success response.
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
     * Error response.
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
