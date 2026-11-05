# Local installation before publication

Neither package is on a public Composer registry yet. The package names are development names.

For a standalone consumer, add this repository as a path repository (replace the absolute path):

```json
{
  "repositories": [
    {"type":"path","url":"/absolute/path/PayamBridge","options":{"versions":{"payambridge/sdk":"dev-main"}}}
  ],
  "require": {"payambridge/sdk":"dev-main"}
}
```

Run `composer update`. For Laravel, also add:

```json
{"type":"path","url":"/absolute/path/PayamBridge/packages/laravel","options":{"versions":{"payambridge/laravel":"dev-main"}}}
```

and require `"payambridge/laravel":"dev-main"`. In CI consumer checks, `"symlink": false` forces a copied install, so missing files and accidental development autoload dependencies are visible. Applications normally commit their own Composer lock file.

The SDK's root `composer.lock` locks development verification. It is not shipped in the source archive and does not constrain a library consumer's resolution. `packages/laravel` is a separate package and must be installed separately; no framework integration loads when only the SDK is required.

The development matrix is PHP 8.3–8.5 and Laravel 12/13. The lock checked into this initial checkout was resolved on PHP 8.5. Use `composer update` for other matrix cells; CI resolves dependencies independently per target. Dependencies installed for development include Testbench/Laravel, but a normal `payambridge/sdk` installation has no framework dependency.

Use `composer check` in this repository. `composer analyse` runs PHPStan sequentially (`--debug`) so it also works in environments that prohibit even local TCP sockets. No secret, login, migration, npm build or daemon is executed by either package's installation.
