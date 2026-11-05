<?php

declare(strict_types=1);

namespace PayamBridge\Drivers\Eitaa;

use PayamBridge\Contracts\Capability;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\SendsText;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\CapabilitySet;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\MessageReceipt;
use PayamBridge\Drivers\NativeClient;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\OperationContext;
use PayamBridge\Support\Wire;

final class EitaaApp extends NativeClient implements SendsText
{
    protected const Provider PROVIDER = Provider::Eitaa;

    protected const Surface SURFACE = Surface::App;

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(Capability::SendsText);
    }

    protected function endpoint(string $method): string
    {
        return 'https://eitaayar.ir/api/app/'.$method;
    }

    protected function unwrap(array $body, OperationContext $context): mixed
    {
        if (($body['ok'] ?? null) === false) {
            throw new SdkException(ErrorKind::Provider, $context);
        }
        if (($body['ok'] ?? null) !== true || ($body['result'] ?? null) !== 'success') {
            throw new SdkException(ErrorKind::Protocol, $context);
        }

        return $body;
    }

    public function sendText(ChatRef $chat, string $text): MessageReceipt
    {
        return $this->sendMessage($chat, $text);
    }

    public function sendMessage(ChatRef $chat, string $text): MessageReceipt
    {
        $this->assertScope($chat);
        Wire::text($text);
        $id = Wire::integer($chat->id);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Eitaa application chat ID must be a positive integer.');
        }

        return $this->execute('sendMessage', ['token' => $this->credential->reveal(), 'chat_id' => $id, 'text' => $text], true, static function (mixed $result, OperationContext $context) use ($chat): MessageReceipt {
            return new MessageReceipt($chat, null, Wire::object($result, $context));
        });
    }
}
