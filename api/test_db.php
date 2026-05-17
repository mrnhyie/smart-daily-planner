<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$results = [
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'db_connection' => getenv('DB_CONNECTION'),
    'db_url_exists' => !empty(getenv('DB_URL')),
    'database_url_exists' => !empty(getenv('DATABASE_URL')),
];

try {
    $dbUrl = getenv('DB_URL') ?: getenv('DATABASE_URL');
    if ($dbUrl) {
        $parsed = parse_url($dbUrl);
        $results['parsed_url'] = [
            'scheme' => $parsed['scheme'] ?? null,
            'host' => $parsed['host'] ?? null,
            'port' => $parsed['port'] ?? null,
            'user' => isset($parsed['user']) ? 'exists' : 'missing',
            'pass' => isset($parsed['pass']) ? 'exists' : 'missing',
            'path' => $parsed['path'] ?? null,
        ];
        
        $dsn = "pgsql:host=" . $parsed['host'] . ";port=" . ($parsed['port'] ?? 5432) . ";dbname=" . ltrim($parsed['path'], '/') . ";sslmode=require";
        $pdo = new PDO($dsn, $parsed['user'], $parsed['pass']);
        $results['connection_status'] = 'success';
        
        $stmt = $pdo->query("SELECT CURRENT_TIMESTAMP");
        $results['query_result'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $results['connection_status'] = 'no_db_url_provided';
    }
} catch (Exception $e) {
    $results['connection_status'] = 'failed';
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
