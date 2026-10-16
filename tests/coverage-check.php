<?php

declare(strict_types=1);

/**
 * Fail the build when line coverage in a Clover report is below the threshold.
 *
 * Usage: php tests/coverage-check.php <clover.xml> [minimum-percent=90]
 */

$cloverFile = $argv[1] ?? '';
$threshold = (float) ($argv[2] ?? 90);

if ($cloverFile === '' || !is_file($cloverFile)) {
    fwrite(STDERR, "Clover report not found: {$cloverFile}\n");
    exit(1);
}

$xml = simplexml_load_file($cloverFile);
if ($xml === false) {
    fwrite(STDERR, "Cannot parse Clover report: {$cloverFile}\n");
    exit(1);
}

$metrics = $xml->xpath('/coverage/project/metrics')[0] ?? null;
if ($metrics === null) {
    fwrite(STDERR, "Clover report has no project metrics.\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements > 0 ? $covered / $statements * 100 : 0.0;

printf("Line coverage: %.2f%% (%d/%d), required: %.2f%%\n", $percent, $covered, $statements, $threshold);

exit($percent >= $threshold ? 0 : 1);
