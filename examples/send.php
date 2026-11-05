<?php

declare(strict_types=1);

use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Exceptions\AmbiguousDeliveryException;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Security\Credential;

require __DIR__.'/../vendor/autoload.php';

$token = getenv('BALE_BOT_TOKEN');
$chatId = getenv('PAYAMBRIDGE_TEST_CHAT_ID');
if (! is_string($token) || $token === '' || ! is_string($chatId) || $chatId === '') {
    fwrite(STDERR, 'Set BALE_BOT_TOKEN and PAYAMBRIDGE_TEST_CHAT_ID to an authorized test destination.'.PHP_EOL);
    exit(2);
}

$id = new ConnectionId(Provider::Bale, Surface::Bot, 'example');
$bot = new BaleBot($id, new Credential($token), HttpTransport::createDefault());
try {
    $receipt = $bot->sendText(new ChatRef($id, $chatId), 'سلام از پیام‌بریج');
    fwrite(STDOUT, 'Provider message ID: '.$receipt->message?->id.PHP_EOL);
} catch (AmbiguousDeliveryException) {
    fwrite(STDERR, 'Delivery outcome is unknown. Check the destination before retrying.'.PHP_EOL);
    exit(3);
}
