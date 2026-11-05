# PayamBridge Laravel bridge

Development package; not published yet. Integrates the separate `payambridge/sdk` package with Laravel 12/13 (target matrix) on PHP 8.3–8.5.

Features: auto-discovered provider, named immutable scoped connections, replaceable credential resolver, optional facade, text notification channel and read-only local `payam:capabilities` command. Config stores environment variable names rather than tokens. Publish it with `php artisan vendor:publish --tag=payambridge-config`.

When config is cached, provide referenced credentials in the real process environment or replace `CredentialResolver`. Resolve clients inside job handlers. Never serialize a client or blindly retry a notification after `AmbiguousDeliveryException`: delivery may have succeeded.

No routes, migrations or daemons are installed automatically. Webhook, user-session and Mini App integrations are not implemented in this initial slice. See the monorepo's `docs/laravel.md`, `docs/installation.md` and `docs/IMPLEMENTATION_STATUS.md` for current usage and executed verification. MIT.
