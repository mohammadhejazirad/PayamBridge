# API reference (initial supported scope)

The API below covers implemented operations, not all provider APIs. Contract tests use documented fixtures; a successful request with a valid live credential is not asserted here. Wire sources and unresolved discrepancies are tracked in [research/api-inventory.json](../research/api-inventory.json) and [research/doc-conflicts.md](../research/doc-conflicts.md).

## Common model

ConnectionFactory::create(ConnectionId, Credential) selects only bale/bot, rubika/bot, or eitaa/app. ConnectionId(provider, surface, name, tenant) is immutable; name and tenant accept 1–128 ASCII letters, digits, periods, underscores, and hyphens. ChatRef binds a nonempty UTF-8 chat ID to that full scope. A driver rejects a foreign ChatRef before HTTP. The host application must still authorize who can choose a connection.

Connection::id() and capabilities() expose the scoped identity and implemented CapabilitySet. Separate interfaces are SendsText::sendText(ChatRef, string): MessageReceipt, GetsIdentity::getMe(): BotIdentity, and ReceivesUpdates::fetchUpdates(?string): UpdateBatch. Supported capability values are sends_text, gets_identity, receives_updates, and durable_polling; only Bale has durable_polling. Use supports() or require() to check a capability.

MessageReceipt has chat, nullable MessageRef, and sensitive raw response. BotIdentity has connection, ID, optional name/username, and raw model. UpdateBatch has events and nullable nextCursor. IncomingEvent has connection, nullable stable ID, kind, optional chat/message/text, and raw fields. Unknown fields and event kinds remain in raw. Eitaa App's MessageReceipt::message is null because the documented response has no message ID.

## Bale Bot

Endpoint: https://tapi.bale.ai/bot{token}/{method}. JSON POST to this fixed origin.

- getMe(): BotIdentity. Requires a provider user ID; name and username are optional.
- sendMessage(ChatRef $chat, string $text, ?string $replyToMessageId = null, ?array $replyMarkup = null): MessageReceipt. Nonempty UTF-8 text, locally limited to 4096 Unicode code points. reply ID must fit a wire integer. Native Bale markup is passed as an array. Explicit zero and non-null optional arguments remain in the payload. A @channel destination may resolve to a numeric chat ID in the receipt. This sends a message and is not retried automatically.
- getUpdates(?string $offset = null, int $limit = 100, ?int $timeout = null): UpdateBatch. limit 1–100; offset is a wire integer; timeout is nonnegative seconds. nextCursor is the greatest update_id plus one, regardless of order. An empty batch retains the prior cursor. Passing a newer offset acknowledges earlier updates at the provider. Negative offsets can discard earlier updates; the Poller does not invent one.
- sendText is the shared simple-send alias. fetchUpdates calls getUpdates without a long-poll timeout. For native long polling, set read/total transport timeouts longer than the requested provider timeout.

Bale can be collected with Poller and SqliteInboxStore. Poller::once acquires a lease, fetches one batch, and atomically stores its events and cursor. SqliteInboxStore::initialize explicitly creates its tables; events(ConnectionId, limit) reads stored raw JSON rows, with limit 1–1000. Stable IDs deduplicate rows. This is collection, not business-event dispatch. Do not run webhook and polling concurrently without an explicit provider-management workflow.

## Rubika Bot

Endpoint: https://botapi.rubika.ir/v3/{token}/{method}. JSON POST to this fixed origin. Official method and reference-client response shapes conflict in some places; see the research discrepancies.

- getMe(): BotIdentity. Accepts documented data.bot and reference-client direct data shapes; bot_id is required.
- sendMessage(ChatRef $chat, string $text, ?bool $disableNotification = null, ?string $replyToMessageId = null, ?array $inlineKeypad = null, ?array $chatKeypad = null, ?string $chatKeypadType = null): MessageReceipt. Text must be nonempty UTF-8. Native keypad arrays are forwarded; chatKeypadType accepts New or Remove. Explicit false, string zero, and empty nested rows are retained. No undocumented text maximum or Telegram markup is imposed. This sends a message without automatic retry.
- getUpdates(?string $offsetId = null, int $limit = 10): UpdateBatch. limit must be positive; no undocumented upper bound is imposed. The opaque next_offset_id is preserved as a string. Event kind is the original type; event ID is null because the documented Update lacks a stable ID. No timeout parameter is invented.
- sendText and fetchUpdates are the shared aliases. Durable Poller use throws UnsupportedCapabilityException until a stable event identity is verified.

Unknown non-OK provider status is classified as a provider error. A safe uppercase status may appear as providerCode; error descriptions and payloads are omitted from exception text.

## Eitaa App

Endpoint: https://eitaayar.ir/api/app/sendMessage. This identity differs from EitaaYar channel publishing.

sendMessage(ChatRef $chat, string $text): MessageReceipt sends the app token, a positive integer chat_id, and nonempty UTF-8 text. The recipient must have granted permission. Only the documented ok=true and result=success response is accepted. The receipt has no fabricated message ID. sendText is the shared alias. No getMe, getUpdates, channel-file, or personal-account method is exposed.

## Errors and HTTP behavior

SdkException exposes kind (ErrorKind), public operation/connection/correlation context, and optional providerCode and retryAfter. Kinds: transport, timeout, provider, authorization, permission, validation, rate_limit, protocol_mismatch, delivery_unknown. Invalid local arguments throw InvalidArgumentException; absent capabilities throw UnsupportedCapabilityException. A read network error can be transport/timeout; malformed read responses are protocol_mismatch.

Writes can raise AmbiguousDeliveryException (delivery_unknown) after a network failure, 5xx/redirect response, or malformed successful response, because the provider may have accepted the request. No automatic retries run, including for rate limits. Reconcile before retrying. The default Guzzle transport verifies TLS, rejects redirects, and uses Timeouts(connect=5, read=30, total=35 seconds). HTTP response bodies have a default 4 MiB local cap. This is an SDK resource policy, not a provider limit. A custom PSR-18 client must set its own equivalent security/timeout behavior. Raw models and fake requests can contain secrets or personal data; apply your own access and retention policy.
