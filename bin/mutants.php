<?php // bin/mutants.php <infection json log>
PHP_SAPI === 'cli' || exit;

// Prints a mutation run as lines two runs can `diff`: the totals, then one sorted line per survivor.
// A survivor is escaped, timed out or not covered; skipped mutants are not in the log, only counted.

const SURVIVORS = ['escaped' => 'escapedCount', 'timeouted' => 'timeOutCount', 'uncovered' => 'notCoveredCount'];

$root = dirname(__DIR__) . '/';
$log = json_decode((string) file_get_contents($argv[1] ?? $root . 'build/infection/mutation.json'), true, 512, JSON_THROW_ON_ERROR);

$lines = [];
foreach (SURVIVORS as $status => $count) {
    $entries = $log[$status] ?? [];
    if (count($entries) !== $log['stats'][$count]) {
        fwrite(STDERR, sprintf("%s: %d entries, but stats.%s is %d\n", $status, count($entries), $count, $log['stats'][$count]));
        exit(1);
    }
    foreach ($entries as $entry) {
        $mutator = $entry['mutator'];
        $lines[] = implode(' ', [
            $status,
            $mutator['mutatorName'],
            str_replace($root, '', $mutator['originalFilePath']),
            $mutator['originalStartLine'],
            substr(sha1($entry['diff']), 0, 12),
        ]);
    }
}
sort($lines);

printf("total %d skipped %d\n", $log['stats']['totalMutantsCount'], $log['stats']['skippedCount']);
foreach ($lines as $line) {
    echo $line, "\n";
}
