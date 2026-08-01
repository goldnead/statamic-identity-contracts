# Changelog

## Unreleased

### Major changes

- **Laravel 11 is no longer supported.** `require` is now `^12.0|^13.0`. Every
  `laravel/framework` v11 release (v11.0.0 through v11.55.0) is covered by
  security advisories, so Composer refuses to install the line under its default
  policy — the previous `^11.0` branch of the constraint was unsatisfiable
  rather than merely untested.
- **This package is no longer declared as a Statamic addon.** `type` is now
  `library` and `extra.statamic` is gone. It requires no `statamic/cms`,
  references no Statamic class and has no Control Panel surface, so a Marketplace
  listing would advertise a capability that does not exist. It ships on Packagist
  as a dependency of the addons that need it. No runtime behaviour changes.

### What's fixed

- `@param array<string, mixed> $meta` added to the four `Identity` named
  constructors; only the constructor itself carried the type.
- The `with*()` copy modifiers now pass named constructor arguments instead of
  positional ones, so a future constructor parameter cannot silently shift
  values into neighbouring fields.

### Tooling

- Pint (Laravel preset), PHPStan/Larastan at level 8 with an empty baseline, and
  a `.gitattributes` that keeps tests and tool config out of the published
  package.
- CI now runs PHP 8.2/8.3/8.4 × Laravel 12/13 × prefer-lowest/prefer-stable, plus
  a style and static-analysis job. Previously only PHP was varied.
- `SECURITY.md` added.

## 1.0.0 — 2026-07-26

### Added — identity foundation

- **`Identity` value object.** A readonly bag of scalars (`type`, `id`, `userId`, `contactUuid`, `email`, `name`, `anonymousId`, `meta`) describing *who* did something. Safe to persist and to put on a queue: nothing resolves lazily, so a record stays readable after the underlying model is renamed, merged or deleted. Named constructors for the four built-in types, copy-on-write modifiers, and lossless `toArray()`/`fromArray()` whose keys match the column names a persisting consumer wants.
- **`IdentityContext` facade + `IdentityManager`.** `current()` (never null), `resolve()` for arbitrary subjects, `actingAs()` for jobs and imports that run outside the request that caused them, `withContact()` for CRM enrichment.
- **Four contracts.** `ProvidesIdentity` (host models describe themselves), `IdentityResolver` (teach the manager new subject types), `ContactLocator` (email → CRM contact), `AnonymousIdResolver` (pseudonymous visitor id).
- **Inert defaults.** `NullContactLocator` misses every lookup; `SessionAnonymousIdResolver` reuses the existing session and sets no cookie of its own. Both mean a consumer can always ask, whether or not the application has a CRM or anonymous tracking.
- **`pseudonymised()`** drops `email`, `name` and `meta` while keeping the join keys — the retention/anonymisation primitive for downstream consumers.

### Notes

- Contracts-only package: no migrations, no models, no CP surface. It owns no data, so there is nothing to permission.
- Unrecognised subjects degrade to the fallback instead of throwing. Identity is metadata; a ledger write must never fail because an actor could not be classified.
- Suite green: **26 passed (63 assertions)** — value-object semantics, the full resolution chain, resolver precedence, nested/throwing `actingAs`, auth-guard opt-out, and all four anonymous-id modes.
