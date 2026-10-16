<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/c2pa.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const MAX_IMAGE_BYTES = 2097152;
const MAX_IMAGE_PIXELS = 40000000;
const MAX_IMAGE_SIDE = 10000;
const RATE_LIMIT = 5;
const RATE_WINDOW = 60;
const INFERENCE_TIMEOUT = 120;

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function logSecurity(string $event): void
{
    error_log('[security] ' . substr($event, 0, 300));
}

function takeRateLimit(): bool
{
    $dir = '/tmp/ai-detector-limits';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
    $key = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $handle = @fopen($dir . '/' . $key, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return false;
    $now = time();
    $state = json_decode((string) stream_get_contents($handle), true);
    if (!is_array($state) || !isset($state['start'], $state['count']) || $now - (int) $state['start'] >= RATE_WINDOW) {
        $state = ['start' => $now, 'count' => 0];
    }
    $allowed = (int) $state['count'] < RATE_LIMIT;
    if ($allowed) $state['count']++;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}

function dbSave(array $predictions): void
{
    $scores = [];
    foreach ($predictions as $item) {
        if (!is_array($item)) continue;
        $label = strtolower((string) ($item['label'] ?? ''));
        $value = $item['score'] ?? null;
        if (in_array($label, ['artificial', 'human'], true) && is_numeric($value)) {
            $score = (float) $value;
            if (!is_finite($score) || $score < 0 || $score > 1) throw new RuntimeException('Invalid model score');
            $scores[$label] = $score;
        }
    }
    if (!isset($scores['artificial'], $scores['human'])) throw new RuntimeException('Missing model predictions');
    $pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=ai_image_detector;charset=utf8mb4', 'www-data', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $stmt = $pdo->prepare('INSERT INTO analysis_results (prediction, ai_score, human_score) VALUES (?, ?, ?)');
    $stmt->execute([$scores['artificial'] >= $scores['human'] ? 'artificial' : 'human', $scores['artificial'], $scores['human']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'error' => 'Only POST requests are allowed.']);
}
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url((string) $_SERVER['HTTP_ORIGIN']);
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $requestScheme = $isHttps ? 'https' : 'http';
    $requestHost = strtolower((string) parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $requestPort = (int) ($_SERVER['SERVER_PORT'] ?? ($isHttps ? 443 : 80));
    $originScheme = strtolower((string) ($origin['scheme'] ?? ''));
    $originHost = strtolower((string) ($origin['host'] ?? ''));
    $originPort = (int) ($origin['port'] ?? ($originScheme === 'https' ? 443 : 80));
    if ($originHost === '' || $originScheme !== $requestScheme || $originPort !== $requestPort || !hash_equals($requestHost, $originHost)) {
        logSecurity('cross-origin analysis request rejected');
        respond(403, ['success' => false, 'error' => 'Cross-origin requests are not allowed.']);
    }
}
if (!takeRateLimit()) {
    logSecurity('analysis rate limit exceeded');
    header('Retry-After: ' . RATE_WINDOW);
    respond(429, ['success' => false, 'error' => 'Too many analysis requests. Please try again shortly.']);
}
if (!isset($_FILES['image']) || !is_array($_FILES['image']) || is_array($_FILES['image']['error'] ?? null)) {
    logSecurity('missing or malformed image upload');
    respond(400, ['success' => false, 'error' => 'No valid image was uploaded.']);
}
$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $tooLarge = in_array(($file['error'] ?? 0), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    logSecurity('upload rejected, PHP upload error ' . (int) $file['error']);
    respond($tooLarge ? 413 : 400, ['success' => false, 'error' => $tooLarge ? 'Image must be 2 MB or smaller.' : 'Image upload failed.']);
}
$tmp = $file['tmp_name'] ?? '';
if (!is_uploaded_file($tmp) || (int) $file['size'] < 1 || (int) $file['size'] > MAX_IMAGE_BYTES) {
    logSecurity('empty, oversized, or invalid temporary upload');
    respond((int) ($file['size'] ?? 0) > MAX_IMAGE_BYTES ? 413 : 400, ['success' => false, 'error' => 'Image must be a non-empty image of 2 MB or smaller.']);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$formats = ['image/jpeg' => ['jpg', IMAGETYPE_JPEG], 'image/png' => ['png', IMAGETYPE_PNG], 'image/webp' => ['webp', IMAGETYPE_WEBP]];
$imageInfo = @getimagesize($tmp);
if (!isset($formats[$mime]) || !$imageInfo || $imageInfo[2] !== $formats[$mime][1] || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > MAX_IMAGE_SIDE || $imageInfo[1] > MAX_IMAGE_SIDE || $imageInfo[0] * $imageInfo[1] > MAX_IMAGE_PIXELS) {
    logSecurity('unsupported or malformed image rejected');
    respond(400, ['success' => false, 'error' => 'Only valid JPEG, PNG or WebP images up to 40 megapixels are allowed.']);
}

$workDir = '/tmp/ai-detector-uploads';
if (!is_dir($workDir) && !@mkdir($workDir, 0700, true) && !is_dir($workDir)) respond(503, ['success' => false, 'error' => 'Analysis service is unavailable.']);
$imagePath = $workDir . '/' . bin2hex(random_bytes(20)) . '.' . $formats[$mime][0];
$lock = @fopen('/tmp/ai-detector-inference.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    if (is_resource($lock)) fclose($lock);
    respond(503, ['success' => false, 'error' => 'Analysis is busy. Please retry shortly.']);
}
$process = null;
$responseStatus = 500;
$responseData = ['success' => false, 'error' => 'Image analysis failed.'];
$wasTimedOut = false;
try {
    if (!move_uploaded_file($tmp, $imagePath)) throw new RuntimeException('Could not safely move uploaded image');
    $command = ['/usr/bin/timeout', '--signal=TERM', '--kill-after=5s', INFERENCE_TIMEOUT . 's', '/opt/ai-venv/bin/python', '/opt/ai-detector/detector.py', $imagePath];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMP_NUM_THREADS' => '2', 'MKL_NUM_THREADS' => '2', 'OPENBLAS_NUM_THREADS' => '2']);
    if (!is_resource($process)) throw new RuntimeException('Could not start detector');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = ''; $errors = ''; $deadline = microtime(true) + INFERENCE_TIMEOUT + 8; $timedOut = false; $observedExitCode = -1;
    do {
        $read = [$pipes[1], $pipes[2]]; $write = null; $except = null;
        @stream_select($read, $write, $except, 0, 200000);
        foreach ($read as $pipe) {
            $chunk = stream_get_contents($pipe);
            if ($pipe === $pipes[1]) $output .= $chunk; else $errors .= $chunk;
        }
        $state = proc_get_status($process);
        if (!$state['running']) $observedExitCode = (int) $state['exitcode'];
        if (strlen($output) > 65536 || strlen($errors) > 65536) { proc_terminate($process, 9); throw new RuntimeException('Detector output limit exceeded'); }
        if (microtime(true) >= $deadline && $state['running']) { $timedOut = true; proc_terminate($process, 9); break; }
    } while ($state['running']);
    foreach ([1, 2] as $i) { $chunk = stream_get_contents($pipes[$i]); if ($i === 1) $output .= $chunk; else $errors .= $chunk; fclose($pipes[$i]); }
    $exitCode = proc_close($process); $process = null;
    if ($exitCode < 0 && $observedExitCode >= 0) $exitCode = $observedExitCode;
    if ($timedOut || $exitCode === 124 || $exitCode === 137) { $wasTimedOut = true; throw new RuntimeException('Inference deadline exceeded'); }
    if ($exitCode !== 0) { logSecurity('detector execution failed: ' . $errors); throw new RuntimeException('Detector execution failed'); }
    $predictions = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($predictions) || !$predictions) throw new RuntimeException('Invalid detector response');
    dbSave($predictions);
    $c2pa = inspectC2pa($imagePath);
    $responseStatus = 200;
    $responseData = ['success' => true, 'predictions' => $predictions, 'c2pa' => $c2pa, 'synthid' => ['status' => 'not_configured', 'message' => 'SynthID detection is not connected.']];
} catch (Throwable $e) {
    logSecurity('analysis failed: ' . $e->getMessage());
    $responseStatus = $wasTimedOut ? 504 : 500;
    $responseData = ['success' => false, 'error' => $wasTimedOut ? 'Image analysis timed out.' : 'Image analysis failed.'];
} finally {
    if (is_resource($process)) { proc_terminate($process, 9); @proc_close($process); }
    if (is_file($imagePath)) @unlink($imagePath);
    flock($lock, LOCK_UN);
    fclose($lock);
}
respond($responseStatus, $responseData);
