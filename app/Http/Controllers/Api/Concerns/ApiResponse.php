<?php

namespace App\Http\Controllers\Api\Concerns;

trait ApiResponse
{
    protected function successResponse($data = null, $message = 'Success', $status = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $extra), $status);
    }

    protected function errorResponse($message = 'Error', $status = 422, $data = null, array $extra = [])
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $extra), $status);
    }
}
