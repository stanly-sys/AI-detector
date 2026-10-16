<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Bound repeated aggregate queries from the same client.
$limitFile = '/tmp/ai-detector-stats-' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$limitHandle = @fopen($limitFile, 'c+');
if (!$limitHandle || !flock($limitHandle, LOCK_EX)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Statistics are temporarily unavailable.']);
    exit;
}
$now = time();
$limitState = json_decode((string) stream_get_contents($limitHandle), true);
if (!is_array($limitState) || $now - (int) ($limitState['start'] ?? 0) >= 60) $limitState = ['start' => $now, 'count' => 0];
if ((int) $limitState['count'] >= 30) {
    error_log('[security] statistics rate limit exceeded');
    flock($limitHandle, LOCK_UN); fclose($limitHandle);
    http_response_code(429); header('Retry-After: 60');
    echo json_encode(['success' => false, 'error' => 'Too many statistics requests.']);
    exit;
}
$limitState['count']++;
ftruncate($limitHandle, 0); rewind($limitHandle); fwrite($limitHandle, json_encode($limitState)); fflush($limitHandle);
flock($limitHandle, LOCK_UN); fclose($limitHandle);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_log('[security] unsupported statistics request method');
    http_response_code(405);
    header('Allow: GET');
    echo json_encode([
        'success' => false,
        'error' => 'Only GET requests are allowed.'
    ]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=ai_image_detector;charset=utf8mb4',
        'www-data',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $query = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(prediction = 'artificial'), 0) AS ai_total,
            COALESCE(SUM(prediction = 'human'), 0) AS human_total,
            COALESCE(AVG(ai_score), 0) AS avg_ai_score
        FROM analysis_results
    ";

    $stats = $pdo->query($query)->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total' => (int) $stats['total'],
            'ai_total' => (int) $stats['ai_total'],
            'human_total' => (int) $stats['human_total'],
            'average_ai_score' => round(
                (float) $stats['avg_ai_score'] * 100,
                2
            )
        ]
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    error_log('Statistics API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Could not load statistics.'
    ]);
}
