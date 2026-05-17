<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Fix Vercel serverless SCRIPT_NAME routing prefix bug
if (isset($_SERVER['NOW_REGION']) || isset($_SERVER['HTTP_X_VERCEL_DEPLOYMENT_URL'])) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS, POST, PUT, DELETE, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
    header("HTTP/1.1 200 OK");
    exit();
}

if (isset($_GET['debug_server'])) {
    header("Content-Type: application/json");
    echo json_encode($_SERVER, JSON_PRETTY_PRINT);
    exit();
}

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        'error_class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ], JSON_PRETTY_PRINT);
}
