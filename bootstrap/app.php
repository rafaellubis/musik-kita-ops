<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // [SECURITY] Health check path diubah dari /up agar tidak mudah ditebak
        health: '/health-check',
    )
    ->withMiddleware(function (Middleware $middleware) {
		$middleware->alias([
			'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
			'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
		]);

		// [SECURITY] Security headers global — X-Frame-Options, HSTS, dll.
		$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
	})

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

