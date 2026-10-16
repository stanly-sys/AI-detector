<?php

declare(strict_types=1);

/**
 * Inspect signed C2PA Content Credentials.
 *
 * This is independent of the Python AI classifier.
 */
function inspectC2pa(string $imagePath): array
{
    $unavailable = [
        'status' => 'unavailable',
        'message' => 'C2PA verification could not be completed.'
    ];

    if (!is_executable('/usr/local/bin/c2patool')) {
        error_log('C2PA binary is missing.');
        return $unavailable;
    }

    $command = [
        '/usr/bin/timeout',
        '20s',
        '/usr/local/bin/c2patool',
        $imagePath,
        'trust',
        '--trust_anchors',
        '/etc/c2pa/trust.pem'
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    try {
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return $unavailable;
        }

        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $report = json_decode((string) $output, true);

        // Some unsigned images cause "No claim found".
        if (!is_array($report)) {
            $diagnostic = strtolower(
                (string) $output . "\n" . (string) $errors
            );

            if (
                str_contains($diagnostic, 'no claim found') ||
                str_contains($diagnostic, 'no manifest found')
            ) {
                return [
                    'status' => 'not_found',
                    'message' => 'No C2PA Content Credentials found.'
                ];
            }

            error_log(
                'C2PA tool failed (exit ' . $exitCode . '): ' .
                    substr((string) $errors, 0, 500)
            );

            return $unavailable;
        }

        // Check that an active manifest exists.
        $active = $report['active_manifest'] ?? null;

        if (!is_string($active) || $active === '') {
            return [
                'status' => 'not_found',
                'message' => 'No active Content Credentials found.'
            ];
        }

        // Modern C2PA validation state
        $state = strtolower(
            (string) ($report['validation_state'] ?? '')
        );

        // Read declared creation sources from the active manifest
        $origin = [
            'type' => 'unknown',
            'digitalSourceType' => null
        ];

        $priority = 0;

        $assertions = $report['manifests'][$active]['assertions'] ?? [];

        foreach ($assertions as $assertion) {

            if (
                !is_array($assertion) ||
                !str_starts_with(
                    (string) ($assertion['label'] ?? ''),
                    'c2pa.actions'
                )
            ) {
                continue;
            }

            $actions = $assertion['data']['actions'] ?? [];

            foreach ($actions as $action) {

                $source = $action['digitalSourceType'] ?? null;

                if (!is_string($source)) {
                    continue;
                }

                // Interpret known IPTC digital source types
                $type = match (true) {
                    str_ends_with($source, '/trainedAlgorithmicMedia')
                    => 'ai_generated_declared',

                    str_ends_with($source, '/compositeWithTrainedAlgorithmicMedia')
                    => 'ai_assisted_declared',

                    str_ends_with($source, '/digitalCapture')
                    => 'digital_capture_declared',

                    default => null
                };

                if ($type === null) {
                    continue;
                }

                // Prioritize AI generation over AI assistance
                // and digital capture when several are declared.
                $rank = match ($type) {
                    'ai_generated_declared' => 3,
                    'ai_assisted_declared' => 2,
                    'digital_capture_declared' => 1
                };

                if ($rank > $priority) {
                    $priority = $rank;

                    $origin = [
                        'type' => $type,
                        'digitalSourceType' => $source
                    ];
                }
            }
        }


        $result = match ($state) {
            'trusted' => [
                'status' => 'trusted',
                'message' =>
                'Signed Content Credentials verified against the configured trust anchors.'
            ],

            'valid' => [
                'status' => 'valid_untrusted',
                'message' =>
                'Credentials passed integrity checks, but signer trust is not established.'
            ],

            'invalid' => [
                'status' => 'invalid',
                'message' =>
                'Content Credentials failed verification.'
            ],

            default => [
                'status' => 'present_unverified',
                'message' =>
                'Content Credentials found, but verification status is unavailable.'
            ]
        };
        // Include the image's declared origin
        $result['origin'] = $origin;

        return $result;
    } catch (Throwable $e) {
        error_log('C2PA error: ' . $e->getMessage());
        return $unavailable;
    }
}
