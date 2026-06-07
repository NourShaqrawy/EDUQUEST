<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // مصادقة قنوات البث الخاصة عبر توكن Sanctum (واجهة API لا تعتمد الكوكيز).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // واجهة API فقط: كل الأخطاء تُرجَع JSON بصيغة موحّدة { status, message, ... }.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => true);

        // استثناءات الأعمال المخصّصة.
        $exceptions->render(fn (ApiException $e) => response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], $e->getStatus()));

        // أخطاء التحقّق من المدخلات.
        $exceptions->render(fn (ValidationException $e) => response()->json([
            'status' => 'error',
            'message' => 'البيانات المُدخلة غير صحيحة.',
            'errors' => $e->errors(),
        ], 422));

        // غير مُصادَق عليه.
        $exceptions->render(fn (AuthenticationException $e) => response()->json([
            'status' => 'error',
            'message' => 'يجب تسجيل الدخول.',
        ], 401));

        // غير مُصرَّح له.
        $exceptions->render(fn (AuthorizationException $e) => response()->json([
            'status' => 'error',
            'message' => $e->getMessage() ?: 'لا تملك صلاحية لهذا الإجراء.',
        ], 403));

        // نموذج/مسار غير موجود.
        $exceptions->render(fn (ModelNotFoundException $e) => response()->json([
            'status' => 'error',
            'message' => 'المورد المطلوب غير موجود.',
        ], 404));

        $exceptions->render(fn (NotFoundHttpException $e) => response()->json([
            'status' => 'error',
            'message' => 'المسار أو المورد المطلوب غير موجود.',
        ], 404));

        // بقية أخطاء HTTP (تحافظ على رمز الحالة الأصلي).
        $exceptions->render(function (HttpExceptionInterface $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() ?: 'حدث خطأ في الطلب.',
            ], $e->getStatusCode());
        });
    })->create();
