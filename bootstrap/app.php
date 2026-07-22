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

if (isset($_SERVER['NOW_REGION']) || isset($_SERVER['HTTP_X_VERCEL_DEPLOYMENT_URL']) || isset($_ENV['VERCEL']) || env('LOG_CHANNEL') === 'stderr') {
    $bootstrapPath = '/tmp/bootstrap';
    $storagePath = '/tmp/storage';

    foreach ([
        $bootstrapPath . '/cache',
        $storagePath . '/app',
        $storagePath . '/framework/cache/data',
        $storagePath . '/framework/sessions',
        $storagePath . '/framework/views',
        $storagePath . '/logs',
    ] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    $app->useBootstrapPath($bootstrapPath);
    $app->useStoragePath($storagePath);
}

return $app;
