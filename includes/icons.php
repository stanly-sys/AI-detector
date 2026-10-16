<?php
/** Small inline SVG icon set. Kept separate from the PHP page template. */
declare(strict_types=1);

function icon(string $name, int $size = 20, string $class = ''): string
{
    $paths = [
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'arrow-right' => '<path d="M4 12h16m-7-7 7 7-7 7"/>',
        'arrow-down' => '<path d="M12 4v16m-7-7 7 7 7-7"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'zap' => '<path d="m13 2-3 8H4l8 12 2-8h6L13 2Z"/>',
        'sparkles' => '<path d="m12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7L12 3Zm7 13 .8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16ZM4 1l.7 2.3L7 4l-2.3.7L4 7l-.7-2.3L1 4l2.3-.7L4 1Z"/>',
        'upload-cloud' => '<path d="M20 16.6a5 5 0 0 0-4.6-8A7 7 0 0 0 2 11.7a5 5 0 0 0 2 9.3h3"/><path d="M12 22V12m-4 4 4-4 4 4"/>',
        'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z"/><path d="M14 2v6h6m-12 6 2.5 2.5L16 11"/>',
        'brain' => '<path d="M12 18V5a3 3 0 0 0-6 0 4 4 0 0 0-2 7 4 4 0 0 0 2 7 3 3 0 0 0 6-1Zm0 0V5a3 3 0 0 1 6 0 4 4 0 0 1 2 7 4 4 0 0 1-2 7 3 3 0 0 1-6-1Z"/><path d="M7 10c3 0 3 3 5 3s2-3 5-3"/>',
        'scan-search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5M8 8h5M8 11h3"/>',
        'chart-no-axes-column' => '<path d="M4 20V12m5 8V6m5 14V9m5 11V3"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 11v6m0-10h.01"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 7v5m0 4h.01"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="9" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'refresh' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A8 8 0 0 1 19 7l1 5M4 12l1 5a8 8 0 0 0 13.4-2"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    ];
    $safeSize = max(12, min(64, $size));
    $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    return '<svg class="icon ' . $safeClass . '" width="' . $safeSize . '" height="' . $safeSize . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

function esc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
