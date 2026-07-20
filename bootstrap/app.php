<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('admin.auth.login'));
        $middleware->alias([
            'search.admin'   => \App\Http\Middleware\RequireSearchAdminRole::class,
            'auth.api_token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'permission'     => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role'           => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Expired CSRF token on admin pages → redirect to login instead of showing a 419.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->is('admin/*')) {
                return redirect()->route('admin.auth.login')
                    ->withErrors(['email' => 'Your session expired. Please sign in again.']);
            }
        });
    })->create();
