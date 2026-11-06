<?php

declare(strict_types=1);

use App\Notifications\SmokeNotification;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use PayamBridge\Laravel\Facades\PayamBridge;
use PayamBridge\Laravel\Notifications\NotificationRoute;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$capturePath = storage_path('logs/payambridge-smoke.jsonl');
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    ensure(config('queue.default') === 'database', 'Database queue was not configured.');
    ensure(PayamBridge::connection('bale')->id()->key() === 'bale:bot:default:bale', 'Discovered facade failed.');
    $target = (new AnonymousNotifiable)->route('payambridge', new NotificationRoute('bale', '123'));
    $target->notifyNow(new SmokeNotification);
    ensure(count(file($capturePath)) === 1, 'Synchronous notification did not send.');
    $target->notify(new SmokeNotification);
    ensure(DB::table('jobs')->count() === 1, 'Queued notification was not persisted.');
    $payload = DB::table('jobs')->value('payload');
    ensure(is_string($payload) && ! str_contains($payload, 'test-token') && ! str_contains($payload, 'worker-only-token'), 'Credential leaked into queue payload.');
    ensure(count(file($capturePath)) === 1, 'Queued notification sent before worker execution.');
    echo "Real application: sync send and database enqueue passed.\n";
} elseif ($mode === 'verify') {
    ensure(DB::table('jobs')->count() === 0 && DB::table('failed_jobs')->count() === 0, 'Worker did not complete the queued notification.');
    $captures = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($capturePath, FILE_IGNORE_NEW_LINES));
    ensure(count($captures) === 2, 'Expected exactly one synchronous and one queued HTTP send.');
    foreach ($captures as $capture) {
        ensure($capture['body'] === ['chat_id' => '123', 'text' => 'سلام از پروژه واقعی لاراول'], 'Notification payload differs from expected wire contract.');
    }
    ensure($captures[0]['uri_sha256'] === hash('sha256', 'https://tapi.bale.ai/bottest-token/sendMessage'), 'Synchronous credential resolution failed.');
    ensure($captures[1]['uri_sha256'] === hash('sha256', 'https://tapi.bale.ai/botworker-only-token/sendMessage'), 'Worker did not resolve fresh process credentials.');
    $version = InstalledVersions::getPrettyVersion('laravel/framework');
    echo "Real Laravel {$version}: separate queue worker, fresh credentials, wire payload and no duplicate send passed.\n";
} else {
    throw new InvalidArgumentException('Expected prepare or verify.');
}
