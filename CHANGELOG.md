# Changelog

## Unreleased — initial development slice

Release preparation and hardening:

- Expand equivalent Persian and English user guides, supported PHP/Laravel versions, installation, notifications, polling, deployment and troubleshooting.
- Sanitize request-construction and response-access/cleanup failures without losing uncertain delivery outcomes; preserve durable inbox cursors on cursorless batches.
- Verify PHP 8.3–8.5 and Laravel 12/13 with repeated suites and real Laravel applications, including a database queue and separate worker.
- Add distribution archive checks and exclude dependency folders, caches and environment files from Composer archives.
- Make the development lock usable on PHP 8.3 while CI resolves against each actual runtime.
- Prepare tag-driven, CI-gated GitHub releases, a separate Laravel subtree distribution, stable SDK dependency resolution and explicit Packagist update requests.
- Add contribution/security guidance and GitHub issue/PR templates. One-time repository/Packagist configuration is documented in `docs/releasing.md`.

Initial functionality:

- Create the Composer SDK skeleton, PSR transport boundaries, scoped references, capability contracts and sanitized delivery errors.
- Add Bale Bot and Rubika Bot identity/text/update wrappers and Eitaa App text sending, with separate provider envelopes.
- Add a single-batch Bale SQLite inbox collector with atomic cursor persistence, replay deduplication and fenced leases.
- Add a separate Laravel package with discovery, config references, scoped resolution, notifications and local capabilities command.
- Add source hashes, partial operation inventory, conflict/license research, deterministic fakes, contract/integration tests and clean consumer verification tooling.

No public release, valid-token live verification, Mini App, personal-account, media, webhook or outbox support is included yet. Full limitations and executed checks are recorded in `docs/IMPLEMENTATION_STATUS.md`.
