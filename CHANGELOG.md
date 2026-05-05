# Changelog

All notable changes to `laravel-watchtower` will be documented in this file.

## [Unreleased]

### Changed (BREAKING — single rename release)

- **Package renamed: `ahmedmerza/logscope-guard` → `ahmedmerza/laravel-watchtower`.** Reflects the strategic pivot away from "LogScope addon" toward "standalone Laravel app-edge blocker, with optional LogScope integration."
- **Namespace renamed: `LogScopeGuard\` → `Watchtower\`.** All `use LogScopeGuard\…` imports must be updated.
- **Service provider renamed: `LogScopeGuardServiceProvider` → `WatchtowerServiceProvider`.**
- **Config file renamed: `config/logscope-guard.php` → `config/watchtower.php`.** Re-run `php artisan vendor:publish --tag="watchtower-config" --force` if you've published a customized version.
- **All env vars renamed: `GUARD_*` → `WATCHTOWER_*`.** Backward-compat aliases are in place for one transition release — `WATCHTOWER_*` takes precedence, but if unset, the old `GUARD_*` values are still consulted. Scheduled to be removed in v1.1.
- **All artisan commands renamed: `guard:install` → `watchtower:install`, `guard:sync` → `watchtower:sync`, `guard:cleanup` → `watchtower:cleanup`.**
- **Cache key renamed: `logscope_guard:blacklist` → `watchtower:blacklist`.** On first boot under the new name, the old Redis key is ignored and a fresh hash is rebuilt from the DB. No data loss; just a brief warm-up window.
- **HTTP routes moved: `/logscope/guard/*` → `/logscope/watchtower/*` (LogScope mode) or `/watchtower/*` (standalone mode).** Update any external callers.
- **Sync wire protocol updated: HMAC signature path `POST/guard/api/block` → `POST/watchtower/api/block` (and the GET equivalent for sync).** Headers renamed: `X-Guard-Signature` → `X-Watchtower-Signature`, `X-Guard-Timestamp` → `X-Watchtower-Timestamp`. **Both master and satellites must be on the same version** — mixing pre/post-rename versions across envs will fail signature verification.

### Added

- **Standalone routing config block (`watchtower.routes.*`).** Previously the package only knew how to mount routes under LogScope's prefix — when LogScope was absent, it still read `config('logscope.routes.prefix', 'guard')` (a leak from a foreign config namespace, working only by virtue of the default). The standalone branch now reads `watchtower.routes.prefix` (default `'watchtower'`), `watchtower.routes.domain`, and `watchtower.routes.middleware` from the package's own config. New `WATCHTOWER_ROUTES_ENABLED` env flag lets users disable the management routes entirely.
- **`watchtower:install` detects LogScope and prints branch-specific next-steps.** Standalone users get a prominent warning that the management routes have no built-in auth until v1.1.

### Notes for migration

- The transitional meta-package `ahmedmerza/logscope-guard` will be published as a thin wrapper that just `require`s `ahmedmerza/laravel-watchtower` for one release, then deprecated. Existing users can upgrade in two steps if needed: first to the meta-package release, then to the new name directly.


