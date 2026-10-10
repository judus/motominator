<?php

// PHPCBF returns 1 after successfully applying fixes.
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../vendor/bin/phpcbf')
    . ' --standard=phpcs.xml';
foreach (array_slice($argv ?? [], 1) as $argument) {
    $command .= ' ' . escapeshellarg($argument);
}
passthru($command, $status);
exit($status <= 1 ? 0 : $status);
