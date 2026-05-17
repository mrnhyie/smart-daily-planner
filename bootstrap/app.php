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
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e) {
            header("Content-Type: application/json");
            echo json_encode([
                'original_exception' => get_class($e),
                'original_message' => $e->getMessage(),
                'original_file' => $e->getFile(),
                'original_line' => $e->getLine(),
                'original_trace' => explode("\n", $e->getTraceAsString()),
            ], JSON_PRETTY_PRINT);
            exit();
        });
    })->create();
