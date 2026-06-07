<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * استجابة JSON موحّدة للنجاح: { status, message, data }.
     * تقبل أي بيانات بما فيها API Resources (تُسلسَل تلقائياً تحت data).
     */
    protected function success(mixed $data = null, string $message = 'success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /** استجابة نجاح بلا حمولة بيانات (رسالة فقط). */
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
        ], $status);
    }
}
