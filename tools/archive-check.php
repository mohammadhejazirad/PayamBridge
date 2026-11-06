<?php

declare(strict_types=1);

// Exercise Composer's distribution builder, including untracked local artifacts:
// a clean git checkout alone cannot catch vendor/cache/secret archive leakage.
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/payambridge-archives-'.bin2hex(random_bytes(6));
if (! mkdir($temporary, 0700)) {
    throw new RuntimeException('Could not create archive check directory.');
}
foreach (['sdk' => $root, 'laravel' => $root.'/packages/laravel'] as $name => $directory) {
    $command = ['composer', 'archive', '--format=zip', '--dir='.$temporary, '--file='.$name, '--working-dir='.$directory, '--no-interaction'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $directory);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start Composer archive.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Composer archive failed for '.$name);
    }
    $archive = new ZipArchive;
    if ($archive->open($temporary.'/'.$name.'.zip') !== true) {
        throw new RuntimeException('Could not inspect Composer archive.');
    }
    foreach (['composer.json', 'LICENSE', 'README.md'] as $required) {
        if ($archive->locateName($required) === false) {
            throw new RuntimeException('Missing '.$required.' in '.$name.' archive.');
        }
    }
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $path = $archive->getNameIndex($index);
        if (preg_match('~^(?:vendor|\.git|\.agents|\.aws|\.codex|\.docs|\.github|\.vscode|\.phpunit\.cache|\.phpstan-cache|coverage|tests|tools)(?:/|$)|^\.env(?:\.|$)|^composer\.lock$~', $path)) {
            throw new RuntimeException('Development or sensitive file leaked into '.$name.' archive: '.$path);
        }
        if ($name === 'sdk' && str_starts_with($path, 'packages/')) {
            throw new RuntimeException('Laravel bridge leaked into standalone SDK archive.');
        }
    }
    $class = $name === 'sdk' ? 'src/ConnectionFactory.php' : 'src/PayamBridgeServiceProvider.php';
    if ($archive->locateName($class) === false || ($name === 'laravel' && $archive->locateName('config/payambridge.php') === false)) {
        throw new RuntimeException('Runtime files missing from '.$name.' archive.');
    }
    echo 'Verified '.$name.' archive: '.$archive->numFiles.' files.'.PHP_EOL;
    $archive->close();
}
echo 'Distribution archives passed in '.$temporary.PHP_EOL;
