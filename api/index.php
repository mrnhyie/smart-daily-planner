<?php

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

require __DIR__ . '/../public/index.php';
