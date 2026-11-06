<?php

declare(strict_types=1);

// Development-only installation check. Creates disposable projects under /tmp;
// no real provider credentials or network API calls are used.
$target = $argv[1] ?? 'php';
if (! in_array($target, ['php', '12', '13'], true)) {
    fwrite(STDERR, 'Usage: php tools/consumer-smoke.php [php|12|13]'.PHP_EOL);
    exit(2);
}
$repository = dirname(__DIR__);
$directory = sys_get_temp_dir().'/payambridge-smoke-'.$target.'-'.bin2hex(random_bytes(5));

function run(array $command, string $directory, array $environment = []): void
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $directory, array_merge(getenv(), $environment));
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start consumer check.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Consumer check command failed: '.$command[0]);
    }
}

if ($target === 'php') {
    mkdir($directory, 0700, true);
    $composer = ['name' => 'payambridge/consumer-smoke', 'require' => []];
} else {
    run(['composer', 'create-project', 'laravel/laravel', $directory, '^'.$target.'.0', '--no-install', '--no-scripts', '--no-interaction', '--quiet'], sys_get_temp_dir());
    $composer = json_decode(file_get_contents($directory.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $composer['require-dev'] = new stdClass;
}
$composer['require']['payambridge/sdk'] = '^0.1';
$composer['repositories'] = [['type' => 'path', 'url' => $repository, 'options' => ['symlink' => false, 'versions' => ['payambridge/sdk' => '0.1.0']]]];
if ($target !== 'php') {
    $composer['require']['payambridge/laravel'] = '^0.1';
    $composer['repositories'][] = ['type' => 'path', 'url' => $repository.'/packages/laravel', 'options' => ['symlink' => false, 'versions' => ['payambridge/laravel' => '0.1.0']]];
}
$composer['config']['allow-plugins'] = false;
file_put_contents($directory.'/composer.json', json_encode($composer, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
run(['composer', 'update', '--no-dev', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress', '--quiet'], $directory);

$smoke = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
if (\Composer\InstalledVersions::isInstalled('phpunit/phpunit') || \Composer\InstalledVersions::isInstalled('orchestra/testbench')) {
    throw new RuntimeException('Development dependency leaked into consumer.');
}
$fake = new \PayamBridge\Testing\FakeHttpClient([new \GuzzleHttp\Psr7\Response(200, [], '{"ok":true,"result":{"id":123,"first_name":"smoke"}}')]);
$id = new \PayamBridge\Data\ConnectionId(\PayamBridge\Contracts\Provider::Bale, \PayamBridge\Contracts\Surface::Bot, 'smoke');
$bot = new \PayamBridge\Drivers\Bale\BaleBot($id, new \PayamBridge\Security\Credential('test-token'), new \PayamBridge\Http\HttpTransport($fake));
if ($bot->getMe()->id !== '123' || count($fake->requests()) !== 1) {
    throw new RuntimeException('Consumer SDK autoload/contract failed.');
}
if (!file_exists(__DIR__.'/artisan') && class_exists(\Illuminate\Support\ServiceProvider::class)) {
    throw new RuntimeException('Laravel leaked into standalone SDK.');
}
if (file_exists(__DIR__.'/artisan')) {
    $app = require __DIR__.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $manager = $app->make(\PayamBridge\Laravel\ConnectionManager::class);
    if ($manager->connection('bale')->id()->key() !== 'bale:bot:default:bale') {
        throw new RuntimeException('Discovered Laravel bridge did not resolve.');
    }
    $cached = file_get_contents($app->getCachedConfigPath());
    if (str_contains($cached, 'test-token') || !str_contains($cached, 'PAYAMBRIDGE_BALE_TOKEN')) {
        throw new RuntimeException('Credential reference did not survive config cache safely.');
    }
}
echo "Consumer autoload, isolation and scoped resolution passed.\n";
PHP;
file_put_contents($directory.'/smoke.php', $smoke);
$environment = ['PAYAMBRIDGE_BALE_TOKEN' => 'test-token'];
if ($target !== 'php') {
    // A real Laravel application, with a database queue and a separate worker
    // process. This provider replaces outbound HTTP, never the notification bus.
    mkdir($directory.'/app/Notifications', 0700, true);
    copy(__DIR__.'/consumer/SmokeNotification.php', $directory.'/app/Notifications/SmokeNotification.php');
    copy(__DIR__.'/consumer/SmokeProvider.php', $directory.'/app/Providers/SmokeProvider.php');
    $providers = require $directory.'/bootstrap/providers.php';
    $providers[] = 'App\\Providers\\SmokeProvider';
    file_put_contents($directory.'/bootstrap/providers.php', '<?php return '.var_export($providers, true).';'.PHP_EOL);
    copy(__DIR__.'/consumer/laravel.php', $directory.'/laravel-smoke.php');
    copy(__DIR__.'/consumer/inbox.php', $directory.'/inbox-smoke.php');
    touch($directory.'/database/smoke.sqlite');
    $environment += [
        'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database/smoke.sqlite',
        'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    ];
    foreach ([['package:discover', '--quiet'], ['vendor:publish', '--tag=payambridge-config', '--quiet'], ['config:cache', '--quiet'], ['route:cache', '--quiet'], ['migrate', '--force', '--quiet'], ['payam:capabilities', 'bale']] as $arguments) {
        run([PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'], $directory, $environment);
    }
}
run([PHP_BINARY, 'smoke.php'], $directory, $environment);
if ($target !== 'php') {
    run([PHP_BINARY, 'laravel-smoke.php', 'prepare'], $directory, $environment);
    // Change the credential after enqueue: the worker must resolve its own
    // environment, rather than a token serialized inside the queued job.
    $workerEnvironment = array_merge($environment, ['PAYAMBRIDGE_BALE_TOKEN' => 'worker-only-token']);
    run([PHP_BINARY, 'artisan', 'queue:work', '--once', '--tries=1', '--no-ansi'], $directory, $workerEnvironment);
    run([PHP_BINARY, 'laravel-smoke.php', 'verify'], $directory, $workerEnvironment);
    foreach (['sqlite', 'file'] as $inboxDriver) {
        $inboxEnvironment = array_merge($environment, ['PAYAMBRIDGE_INBOX_DRIVER' => $inboxDriver]);
        run([PHP_BINARY, 'artisan', 'config:cache', '--quiet', '--no-ansi'], $directory, $inboxEnvironment);
        run([PHP_BINARY, 'inbox-smoke.php', $inboxDriver, 'first'], $directory, $inboxEnvironment);
        run([PHP_BINARY, 'inbox-smoke.php', $inboxDriver, 'replay'], $directory, $inboxEnvironment);
    }
}
fwrite(STDOUT, 'Passed consumer target '.$target.' in '.$directory.PHP_EOL);
