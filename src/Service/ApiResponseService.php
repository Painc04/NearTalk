<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseService
{
    public static function success($data, $message = 'Success', $code = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error($message, $errorCode, $data = null, $code = 400): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
