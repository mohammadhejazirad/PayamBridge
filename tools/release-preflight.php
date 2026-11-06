<?php

declare(strict_types=1);

// Resolve both local packages at the proposed stable version before either tag is published.
$tag = $argv[1] ?? '';
if (! preg_match('/^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $tag)) {
    fwrite(STDERR, "Expected a stable vMAJOR.MINOR.PATCH tag.\n");
    exit(2);
}
$version = substr($tag, 1);
$root = dirname(__DIR__);
$laravel = $root.'/packages/laravel';
foreach ([$root.'/composer.json' => 'payambridge/sdk', $laravel.'/composer.json' => 'payambridge/laravel'] as $file => $name) {
    $manifest = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    if (($manifest['name'] ?? null) !== $name) {
        throw new RuntimeException("Unexpected package name in $file.");
    }
    if ($name === 'payambridge/laravel' && str_contains($manifest['require']['payambridge/sdk'] ?? '', 'dev-')) {
        throw new RuntimeException('Laravel must depend on a stable SDK release, not a development branch.');
    }
}
$temporary = sys_get_temp_dir().'/payambridge-release-'.bin2hex(random_bytes(6));
if (! mkdir($temporary, 0700)) {
    throw new RuntimeException('Could not create release preflight directory.');
}
$consumer = [
    'name' => 'payambridge/release-preflight',
    'require' => ['payambridge/sdk' => $version, 'payambridge/laravel' => $version],
    'repositories' => [
        ['type' => 'path', 'url' => $laravel, 'options' => ['symlink' => false, 'versions' => ['payambridge/laravel' => $version]]],
        ['type' => 'path', 'url' => $root, 'options' => ['symlink' => false, 'versions' => ['payambridge/sdk' => $version]]],
    ],
    'config' => ['allow-plugins' => false],
];
file_put_contents($temporary.'/composer.json', json_encode($consumer, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
$command = ['composer', 'update', '--no-dev', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress', '--working-dir='.$temporary];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $temporary);
if (! is_resource($process)) {
    throw new RuntimeException('Could not start Composer.');
}
fclose($pipes[0]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Stable SDK/Laravel consumer resolution failed; inspect '.$temporary);
}
$installed = json_decode((string) file_get_contents($temporary.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
$names = array_column($installed['packages'] ?? [], 'version', 'name');
foreach (['payambridge/sdk', 'payambridge/laravel'] as $name) {
    if (($names[$name] ?? null) !== $version) {
        throw new RuntimeException("$name did not install at $version; inspect $temporary");
    }
}
fwrite(STDOUT, "Stable release dependency resolution passed for $tag.\n");
