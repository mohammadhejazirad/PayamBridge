<?php

declare(strict_types=1);

// Older supported dependency sets emit PHP 8.4 deprecations while autoloading.
// Keep this fixture's stdout protocol clean, then restore normal reporting for
// all inbox code executed below.
$previousReporting = error_reporting();
error_reporting($previousReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
} finally {
    error_reporting($previousReporting);
}

use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\LeaseUnavailableException;

$store = new FileInboxStore($argv[1]);
$connection = new ConnectionId(Provider::Bale, Surface::Bot, 'orders', 'a');
if (($argv[2] ?? null) === 'signal') {
    echo "ready\n";
    flush();
}
try {
    $lease = $store->acquire($connection, 60);
    echo json_encode(['fence' => $lease->fence], JSON_THROW_ON_ERROR);
    $store->release($lease);
} catch (LeaseUnavailableException $exception) {
    echo json_encode(['error' => $exception::class], JSON_THROW_ON_ERROR);
}
