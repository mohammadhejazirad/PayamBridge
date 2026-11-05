<?php

declare(strict_types=1);

namespace PayamBridge\Drivers\Bale;

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

final class BaleBot extends NativeClient implements GetsIdentity, ReceivesUpdates, SendsText
{
    protected const Provider PROVIDER = Provider::Bale;

    protected const Surface SURFACE = Surface::Bot;

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(Capability::SendsText, Capability::ReceivesUpdates, Capability::GetsIdentity, Capability::DurablePolling);
    }

    protected function endpoint(string $method): string
    {
        return 'https://tapi.bale.ai/bot'.$this->credential->reveal().'/'.$method;
    }

    protected function unwrap(array $body, OperationContext $context): mixed
    {
        if (($body['ok'] ?? null) === false) {
            $code = $body['error_code'] ?? null;
            $kind = match ($code) {
                401 => ErrorKind::Authorization,
                403 => ErrorKind::Permission,
                400, 422 => ErrorKind::Validation,
                429 => ErrorKind::RateLimit,
                default => ErrorKind::Provider,
            };
            $retry = $body['parameters']['retry_after'] ?? null;
            throw new SdkException($kind, $context, is_int($code) ? $code : null, is_int($retry) && $retry >= 0 ? $retry : null);
        }
        if (($body['ok'] ?? null) !== true || ! array_key_exists('result', $body)) {
            throw new SdkException(ErrorKind::Protocol, $context);
        }

        return $body['result'];
    }

    public function getMe(): BotIdentity
    {
        return $this->execute('getMe', [], false, function (mixed $result, OperationContext $context): BotIdentity {
            $raw = Wire::object($result, $context);

            return new BotIdentity($this->connection, Wire::id($raw, 'id', $context), is_string($raw['first_name'] ?? null) ? $raw['first_name'] : null, is_string($raw['username'] ?? null) ? $raw['username'] : null, $raw);
        });
    }

    public function sendText(ChatRef $chat, string $text): MessageReceipt
    {
        return $this->sendMessage($chat, $text);
    }

    /** @param array<string, mixed>|null $replyMarkup Native Bale markup; no Telegram-only fields are added. */
    public function sendMessage(ChatRef $chat, string $text, ?string $replyToMessageId = null, ?array $replyMarkup = null): MessageReceipt
    {
        $this->assertScope($chat);
        Wire::text($text, 4096);
        $payload = ['chat_id' => $chat->id, 'text' => $text];
        if ($replyToMessageId !== null) {
            $payload['reply_to_message_id'] = Wire::integer($replyToMessageId);
        }
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->execute('sendMessage', $payload, true, function (mixed $result, OperationContext $context) use ($chat): MessageReceipt {
            $raw = Wire::object($result, $context);
            $returnedChat = Wire::object($raw['chat'] ?? null, $context);
            $chatId = Wire::id($returnedChat, 'id', $context);
            if (! str_starts_with($chat->id, '@') && $chatId !== $chat->id) {
                throw new SdkException(ErrorKind::Protocol, $context);
            }
            // A @channel alias can resolve to the provider's canonical numeric chat ID.
            $resolved = new ChatRef($chat->connection, $chatId);

            return new MessageReceipt($resolved, new MessageRef($resolved, Wire::id($raw, 'message_id', $context)), $raw);
        });
    }

    public function fetchUpdates(?string $cursor = null): UpdateBatch
    {
        return $this->getUpdates($cursor);
    }

    public function getUpdates(?string $offset = null, int $limit = 100, ?int $timeout = null): UpdateBatch
    {
        if ($limit < 1 || $limit > 100 || ($timeout !== null && $timeout < 0)) {
            throw new InvalidArgumentException('Bale limit must be 1–100 and timeout must be nonnegative.');
        }
        $payload = ['limit' => $limit];
        if ($offset !== null) {
            $payload['offset'] = Wire::integer($offset);
        }
        if ($timeout !== null) {
            $payload['timeout'] = $timeout;
        }

        return $this->execute('getUpdates', $payload, false, function (mixed $result, OperationContext $context) use ($offset): UpdateBatch {
            if (! is_array($result) || ! array_is_list($result)) {
                throw new SdkException(ErrorKind::Protocol, $context);
            }
            $events = [];
            $max = null;
            foreach ($result as $value) {
                $raw = Wire::object($value, $context);
                $id = Wire::id($raw, 'update_id', $context);
                try {
                    $numeric = Wire::integer($id);
                } catch (InvalidArgumentException) {
                    throw new SdkException(ErrorKind::Protocol, $context);
                }
                if ($numeric < 0 || $numeric === PHP_INT_MAX) {
                    throw new SdkException(ErrorKind::Protocol, $context);
                }
                $max = max($max ?? $numeric, $numeric);
                $events[] = $this->event($raw, $id, $context);
            }

            $next = $max === null ? $offset : (string) ($max + 1);
            if ($offset !== null && $next !== null && Wire::integer($offset) > Wire::integer($next)) {
                $next = $offset;
            }

            return new UpdateBatch($events, $next);
        });
    }

    /** @param array<string, mixed> $raw */
    private function event(array $raw, string $id, OperationContext $context): IncomingEvent
    {
        $kind = 'unknown';
        $message = null;
        foreach (['message', 'edited_message'] as $candidate) {
            if (isset($raw[$candidate])) {
                $kind = $candidate;
                $message = Wire::object($raw[$candidate], $context);
                break;
            }
        }
        if (isset($raw['callback_query'])) {
            $kind = 'callback_query';
        }
        if (isset($raw['pre_checkout_query'])) {
            $kind = 'pre_checkout_query';
        }
        $chat = null;
        $ref = null;
        if ($message !== null) {
            $chatData = Wire::object($message['chat'] ?? null, $context);
            $chat = new ChatRef($this->connection, Wire::id($chatData, 'id', $context));
            $ref = new MessageRef($chat, Wire::id($message, 'message_id', $context));
        }

        return new IncomingEvent($this->connection, $id, $kind, $chat, $ref, is_string($message['text'] ?? null) ? $message['text'] : null, $raw);
    }
}
