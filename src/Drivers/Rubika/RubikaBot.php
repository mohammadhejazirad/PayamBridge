<?php

declare(strict_types=1);

namespace PayamBridge\Drivers\Rubika;

use InvalidArgumentException;
use PayamBridge\Contracts\Capability;
use PayamBridge\Contracts\GetsIdentity;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\ReceivesUpdates;
use PayamBridge\Contracts\SendsText;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\BotIdentity;
use PayamBridge\Data\CapabilitySet;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\IncomingEvent;
use PayamBridge\Data\MessageReceipt;
use PayamBridge\Data\MessageRef;
use PayamBridge\Data\UpdateBatch;
use PayamBridge\Drivers\NativeClient;
use PayamBridge\Exceptions\ErrorKind;
use PayamBridge\Exceptions\SdkException;
use PayamBridge\Http\OperationContext;
use PayamBridge\Support\Wire;

final class RubikaBot extends NativeClient implements GetsIdentity, ReceivesUpdates, SendsText
{
    protected const Provider PROVIDER = Provider::Rubika;

    protected const Surface SURFACE = Surface::Bot;

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(Capability::SendsText, Capability::ReceivesUpdates, Capability::GetsIdentity);
    }

    protected function endpoint(string $method): string
    {
        return 'https://botapi.rubika.ir/v3/'.$this->credential->reveal().'/'.$method;
    }

    protected function unwrap(array $body, OperationContext $context): mixed
    {
        $status = $body['status'] ?? null;
        if (! is_string($status)) {
            throw new SdkException(ErrorKind::Protocol, $context);
        }
        if ($status !== 'OK') {
            // The public reference does not specify a complete error enum. Preserve the
            // status as providerCode, without guessing its authorization/retry semantics.
            $safeCode = preg_match('/\A[A-Z_]{1,64}\z/D', $status) ? $status : null;
            throw new SdkException(ErrorKind::Provider, $context, $safeCode);
        }
        if (! array_key_exists('data', $body)) {
            throw new SdkException(ErrorKind::Protocol, $context);
        }

        return $body['data'];
    }

    public function getMe(): BotIdentity
    {
        return $this->execute('getMe', [], false, function (mixed $result, OperationContext $context): BotIdentity {
            $data = Wire::object($result, $context);
            // Both shapes have sources: official method table wraps `bot`; the
            // documentation's Python client v1.0.19 consumes the bot directly.
            $raw = array_key_exists('bot', $data) ? Wire::object($data['bot'], $context) : $data;

            return new BotIdentity($this->connection, Wire::id($raw, 'bot_id', $context), is_string($raw['bot_title'] ?? null) ? $raw['bot_title'] : null, is_string($raw['username'] ?? null) ? $raw['username'] : null, $raw);
        });
    }

    public function sendText(ChatRef $chat, string $text): MessageReceipt
    {
        return $this->sendMessage($chat, $text);
    }

    /**
     * @param  array<string, mixed>|null  $inlineKeypad
     * @param  array<string, mixed>|null  $chatKeypad
     */
    public function sendMessage(ChatRef $chat, string $text, ?bool $disableNotification = null, ?string $replyToMessageId = null, ?array $inlineKeypad = null, ?array $chatKeypad = null, ?string $chatKeypadType = null): MessageReceipt
    {
        $this->assertScope($chat);
        Wire::text($text);
        if ($replyToMessageId === '' || ($chatKeypadType !== null && ! in_array($chatKeypadType, ['New', 'Remove'], true))) {
            throw new InvalidArgumentException('Invalid Rubika reply ID or keypad type.');
        }
        $payload = ['chat_id' => $chat->id, 'text' => $text];
        foreach (['disable_notification' => $disableNotification, 'reply_to_message_id' => $replyToMessageId, 'inline_keypad' => $inlineKeypad, 'chat_keypad' => $chatKeypad, 'chat_keypad_type' => $chatKeypadType] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $this->execute('sendMessage', $payload, true, static function (mixed $result, OperationContext $context) use ($chat): MessageReceipt {
            $raw = Wire::object($result, $context);

            return new MessageReceipt($chat, new MessageRef($chat, Wire::id($raw, 'message_id', $context)), $raw);
        });
    }

    public function fetchUpdates(?string $cursor = null): UpdateBatch
    {
        return $this->getUpdates($cursor);
    }

    public function getUpdates(?string $offsetId = null, int $limit = 10): UpdateBatch
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Rubika limit must be positive. No undocumented upper limit is assumed.');
        }
        $payload = ['limit' => $limit];
        if ($offsetId !== null) {
            $payload['offset_id'] = $offsetId;
        }

        return $this->execute('getUpdates', $payload, false, function (mixed $result, OperationContext $context): UpdateBatch {
            $raw = Wire::object($result, $context);
            if (! is_array($raw['updates'] ?? null) || ! array_is_list($raw['updates']) || ! is_string($raw['next_offset_id'] ?? null)) {
                throw new SdkException(ErrorKind::Protocol, $context);
            }
            $events = [];
            foreach ($raw['updates'] as $value) {
                $event = Wire::object($value, $context);
                $kind = $event['type'] ?? null;
                if (! is_string($kind) || $kind === '') {
                    throw new SdkException(ErrorKind::Protocol, $context);
                }
                $chat = isset($event['chat_id']) ? new ChatRef($this->connection, Wire::id($event, 'chat_id', $context)) : null;
                $messageData = $event['new_message'] ?? $event['updated_message'] ?? null;
                $message = $messageData === null ? null : Wire::object($messageData, $context);
                $ref = $message === null || $chat === null ? null : new MessageRef($chat, Wire::id($message, 'message_id', $context));
                // The documented Update has no event ID. Do not fabricate one from
                // message_id or text: edited messages and identical texts can differ.
                $events[] = new IncomingEvent($this->connection, null, $kind, $chat, $ref, is_string($message['text'] ?? null) ? $message['text'] : null, $event);
            }

            return new UpdateBatch($events, $raw['next_offset_id']);
        });
    }
}
