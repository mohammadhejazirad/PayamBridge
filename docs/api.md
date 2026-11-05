# Initial API reference

All seven operations below have typed wrappers and synthetic source-based contracts. They have **not** passed a valid-token live test. Wire sources and hashes are in `research/api-inventory.json`; remaining operations stay pending. Unknown raw fields are retained. Nested markup/keypad schemas still use native arrays and are not fully validated builders.

## Shared contracts

`Connection::id()` returns immutable `ConnectionId(provider, surface, name, tenant)`. `capabilities()` reports implemented SDK capabilities, not every capability the provider might have. `SendsText::sendText(ChatRef, string): MessageReceipt` is the common text capability. `GetsIdentity::getMe(): BotIdentity` and `ReceivesUpdates::fetchUpdates(?string): UpdateBatch` are separate contracts. Unsupported capabilities fail before HTTP.

`MessageReceipt` contains the resolved chat, optional message reference and raw response. EitaaApp returns no provider message ID, so `message` is null. `IncomingEvent` contains connection, nullable stable event ID, kind, optional chat/message/text and original raw fields. Unknown kinds remain available; Rubika preserves its original type string. Each `UpdateBatch` has events and nullable nextCursor.

## Bale Bot

Origin: `https://tapi.bale.ai/bot{token}/{method}`. Token goes only to this fixed origin. HTTP POST with JSON; empty input is `{}`.

- `getMe(): BotIdentity`: no parameters. Returns User ID, optional name/username and raw User. No write; no retries.
- `sendMessage(ChatRef $chat, string $text, ?string $replyToMessageId = null, ?array $replyMarkup = null): MessageReceipt`: requires a scoped chat and nonempty UTF-8 text, documented maximum 4096 characters (local validation counts Unicode code points; exact upstream unit still needs verification). Optional reply ID is range-checked to a wire integer; markup remains native Bale JSON. Null optional arguments are omitted, zero is preserved. Requires destination permission. Side effect: sends a message. No retries; missing/malformed successful receipt yields `delivery_unknown`.
- `getUpdates(?string $offset = null, int $limit = 100, ?int $timeout = null): UpdateBatch`: limit 1–100, numeric offset range checked, timeout nonnegative seconds when specified. Next offset is maximum returned update_id + 1, independently of ordering. Empty batches retain the prior cursor. **Calling with an advanced offset acknowledges prior updates on the server.** Persist before advancing; `Poller` handles this locally. No automatic retries. Set transport read/total timeouts longer than requested long-polling timeout. Negative offsets are exposed only by this native method and can discard earlier updates; the automatic collector does not invent one.

`sendText` aliases the simple native send; `fetchUpdates` performs periodic polling with no long timeout argument. Do not run concurrent polling and a webhook without an explicit future management workflow.

## Rubika Bot

Origin: `https://botapi.rubika.ir/v3/{token}/{method}`. JSON POST. The status/data envelope has supplemental reference evidence; DOC-09/10 remain open for live confirmation.

- `getMe(): BotIdentity`: accepts documented `data.bot` and the inspected reference client's direct bot `data` shape. Requires bot_id; optional title/username preserved.
- `sendMessage(ChatRef $chat, string $text, ?bool $disableNotification = null, ?string $replyToMessageId = null, ?array $inlineKeypad = null, ?array $chatKeypad = null, ?string $chatKeypadType = null): MessageReceipt`: requires scoped chat and nonempty UTF-8 text. Native keypads remain Rubika objects. Explicit false, string `"0"` and empty nested rows are not filtered out. Keypad type, when specified, is `New` or `Remove`. No undocumented maximum text length or Telegram markup is imposed. Side effect: sends a message; no retry.
- `getUpdates(?string $offsetId = null, int $limit = 10): UpdateBatch`: sends `offset_id`, reads `next_offset_id` as an opaque string, following S14 instead of S13's conflicting start_id. SDK default limit 10 follows R1; official default/upper bound are unknown. No timeout parameter is invented. Incoming event IDs remain null because the official Update model has no stable event ID. Native fetching works, but the durable Poller is deliberately unsupported pending DOC-11.

Unknown status strings are provider errors, without guessed retry or authorization semantics. A known uppercase status may be available as providerCode; provider descriptions and payloads are not exposed in exception messages.

## Eitaa App

Endpoint: `https://eitaayar.ir/api/app/sendMessage`. Separate identity and driver from EitaaYar channel publication.

`sendMessage(ChatRef $chat, string $text): MessageReceipt` sends app token, positive range-checked integer chat_id, and nonempty UTF-8 text. User must have granted permission. Only documented `{"ok":true,"result":"success"}` is accepted; no message ID is invented. This is a write and is never automatically retried. `sendText` is the shared alias. No getMe, getUpdates, channel-file API or personal-account API is exposed.

## Errors and transport

`SdkException` has `kind`, public operation/connection/correlation context, optional providerCode and retryAfter. Kinds include transport, timeout, provider, authorization, permission, validation, rate_limit, protocol_mismatch and delivery_unknown. `AmbiguousDeliveryException` is the distinct unknown-write subclass. Local invalid arguments fail before HTTP. Missing/unknown fields are handled independently: unknown fields survive, missing required identity/receipt/cursor fails.

No automatic retries run, including for rate limits. Do not infer a successful API operation merely from HTTP 200. Malformed write responses and network/write 5xx responses can be ambiguous. The default total timeout bounds buffered PSR-18 requests; Guzzle read_timeout behavior depends on its handler. Inject a PSR-18 client with the required timeout policy for special transports. Upload timeout is not exposed before an upload API exists. Responses have a 4 MiB local safety cap, configurable through HttpTransport; this is an SDK resource policy, not a provider limit.
