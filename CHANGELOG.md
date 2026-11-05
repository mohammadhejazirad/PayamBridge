# Changelog

## Unreleased — initial development slice

- Create the Composer SDK skeleton, PSR transport boundaries, scoped references, capability contracts and sanitized delivery errors.
- Add Bale Bot and Rubika Bot identity/text/update wrappers and Eitaa App text sending, with separate provider envelopes.
- Add a single-batch Bale SQLite inbox collector with atomic cursor persistence, replay deduplication and fenced leases.
- Add a separate Laravel package with discovery, config references, scoped resolution, notifications and local capabilities command.
- Add source hashes, partial operation inventory, conflict/license research, deterministic fakes, contract/integration tests and clean consumer verification tooling.

No public release, valid-token live verification, Mini App, personal-account, media, webhook or outbox support is included yet. Full limitations and executed checks are recorded in `docs/IMPLEMENTATION_STATUS.md`.
