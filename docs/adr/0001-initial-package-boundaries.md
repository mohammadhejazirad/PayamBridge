# ADR 0001: initial package boundaries and safe delivery

Date: 2026-11-06. Status: accepted for initial development.

Keep contracts, core, the first official drivers, test fake and single-batch runtime in `payambridge/sdk` while the public API stabilizes. Keep Laravel under `packages/laravel`, with its own Composer manifest and namespace. Core has no Illuminate dependency and installs without Node, Redis or a database. SQLite is an optional runtime adapter with explicit schema initialization.

Use PHP 8.3 language features and a declared PHP 8.3–8.5 target matrix. Laravel targets 12/13. These are development targets; executed versions and pending CI cells must be reported separately. Consumer installation uses standard PSR-4 autoloading and source distributions, without an install-time daemon or login step.

Use small capability contracts and immutable scoped references. All public external IDs are strings; Eitaa's numeric app chat ID and Bale numeric offsets/reply IDs are range checked at the wire boundary. Native keyboard/markup arrays remain provider-specific until complete model schemas are normalized. Eitaa app receipts have no fabricated message ID.

PSR-18/7/17/3 are the replaceable boundaries. Guzzle/PSR-7 are the current default implementations. Default transport verifies TLS and disables redirects; injected clients must honor PSR-18's no-follow redirect semantics and apply their own verified timeout policy. Request/response payloads, token-bearing URLs and upstream exceptions are never sent to SDK logs. Credentials cannot be serialized. Raw provider models and captured fake requests are deliberately sensitive consumer data.

All requests execute once in this slice. A network failure, HTTP 5xx/redirect, truncated body, or malformed success receipt after a write yields `delivery_unknown`. Provider-declared rejection stays an API error. No generic automatic retry is applied to writes or one-time operations. A future read-retry layer must account for Bale getUpdates acknowledging earlier updates, rather than calling it a side-effect-free read.

Persist events and the provider cursor in the same SQLite `BEGIN IMMEDIATE` transaction. Every checkpoint verifies owner, expiry and a monotonically increasing fence. Release never deletes the fencing row. Poller collects one batch and runs no business handler. This gives replayable, deduplicated collection for Bale; it is not an exactly-once business delivery guarantee. Rubika durable polling remains unsupported until unique event identity is evidenced.

Do not add unverified webhook authentication headers, Mini App verifiers, private protocols or endpoint candidates as working features. Preserve those requirements and blockers explicitly in research and roadmap files.
