# PayamBridge

An Iranian messenger SDK for standalone PHP, with a separate Laravel bridge. **Initial development slice; unpublished and not production-ready.** The full product specification is in `.docs/IRAN_MESSENGER_PHP_AGENT_SPEC_FA.md`.

Implemented native operations:

- Bale Bot: `getMe`, `sendMessage`, `getUpdates`.
- Rubika Bot: `getMe`, `sendMessage`, `getUpdates` (opaque string cursors).
- Eitaa App: `sendMessage` to a user who granted permission. No message ID is fabricated.

PHP 8.3–8.5 and Laravel 12/13 are the target matrix. See [executed verification and limitations](docs/IMPLEMENTATION_STATUS.md) for actual results. No successful operation has been verified with a valid live credential.

```sh
composer install
composer check
```

The core depends on PSR-18/7/17/3, Guzzle and PSR-7, without an Illuminate, Redis, Node or database requirement. Optional SQLite collection persists a Bale batch and cursor atomically with fencing, deduplicates stable update IDs and resumes after restart. It does not dispatch business handlers. Rubika durable collection is explicitly unsupported until event identity is verified.

See [local Composer installation](docs/installation.md), [initial API reference](docs/api.md), [Laravel notifications and workers](docs/laravel.md), and executable [PHP send example](examples/send.php). Real credentials belong in environment/secret storage and test destinations must be authorized.

Public references carry provider, identity surface, connection and tenant scope. Driver instances are immutable and Laravel connections are resolved afresh. Credentials cannot be serialized into jobs. API errors, HTTP errors, protocol mismatches, timeouts and ambiguous writes are separate. No automatic retries run in this slice; consumers must handle `delivery_unknown` explicitly, including queued notifications.

The default HTTP implementation verifies TLS and never follows redirects. A custom PSR-18 implementation is responsible for equivalent timeout/security settings. Raw provider models and captured fake requests are sensitive; SDK logs contain public context only.

Media, webhook ingress/management, EitaaYar, Bale Safir, personal-account protocols, Mini App backend/frontend, encrypted sessions, outbox/dispatcher, AI responder and product publishing remain product requirements. No stub claims these capabilities. Research provenance, unresolved discrepancies and operation coverage are tracked in `research/`; `composer inventory` reports separate implementation, synthetic-contract and live ratios for the current partial inventory.

MIT. Package-name availability and public distribution are not yet verified.
