<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

if (isset($_SERVER['NOW_REGION']) || env('LOG_CHANNEL') === 'stderr') {
    $bootstrapPath = '/tmp/bootstrap';
    if (!is_dir($bootstrapPath . '/cache')) {
        mkdir($bootstrapPath . '/cache', 0755, true);
    }
    $app->useBootstrapPath($bootstrapPath);
}

return $app;
