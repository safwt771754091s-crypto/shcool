<?php

use App\Support\Tenancy\Middleware\InitializeTenancy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only backend with no named "login" web route. The
        // framework default eagerly calls route('login') when an
        // unauthenticated request is rejected, which throws
        // RouteNotFoundException (500). Disable the guest redirect so the
        // AuthenticationException is rendered as a 401 JSON response.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            InitializeTenancy::class,
        ]);

        $middleware->alias([
            'tenant' => InitializeTenancy::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // This is an API-only backend: never try to redirect an unauthenticated
        // request to a named "login" web route (which does not exist), which
        // would surface as a 500 for clients that omit the Accept header.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
