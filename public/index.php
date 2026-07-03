<?php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Controllers/ProductController.php';
require_once __DIR__ . '/../src/Controllers/OrderController.php';
require_once __DIR__ . '/../src/Controllers/StatsController.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$path = parse_url($uri, PHP_URL_PATH);
$basePath = '/refresh_food/public/index.php';

if (str_starts_with($path, $basePath)) {
    $resourcePath = substr($path, strlen($basePath));
} else {
    $resourcePath = $path;
}

$resourcePath = trim($resourcePath, '/');
$segments = $resourcePath === '' ? [] : explode('/', $resourcePath);

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    if ($statusCode === 204 || $data === null) {
        exit;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (count($segments) === 0) {
    jsonResponse(['message' => 'Endpoint non trovato'], 404);
}

$resource = $segments[0] ?? null;
$id = $segments[1] ?? null;

function refreshGlobalCo2Cache(PDO $pdo): void
{
    $sql = "
        SELECT COALESCE(SUM(oi.quantity * p.co2_saved_per_unit), 0) AS total_co2_saved
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
    ";

    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();

    $total = $row ? round((float) $row['total_co2_saved'], 2) : 0.0;

    $stmtUpdate = $pdo->prepare(
        'UPDATE stats_cache SET total_co2_saved = :total WHERE id = 1'
    );
    $stmtUpdate->bindValue(':total', $total);
    $stmtUpdate->execute();
}

try {
    $pdo = Database::getInstance()->getConnection();

    switch ($resource) {
        case 'products':
            (new ProductController($pdo))->processRequest($method, $id);
            break;
        case 'orders':
            (new OrderController($pdo))->processRequest($method, $id);
            break;
        case 'stats':
            if (($segments[1] ?? null) === 'co2') {
                (new StatsController($pdo))->handleCo2($method);
            } else {
                jsonResponse(['message' => 'Endpoint non trovato'], 404);
            }
            break;
        default:
            jsonResponse(['message' => 'Endpoint non trovato'], 404);
    }
} catch (RuntimeException $e) {
    jsonResponse(['message' => $e->getMessage()], 500);
} catch (Throwable $e) {
    jsonResponse(['message' => 'Internal Server Error'], 500);
}