<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(413);
error_log('[security] request rejected by Apache body-size limit');
echo json_encode(['success' => false, 'error' => 'Image must be 2 MB or smaller.']);
