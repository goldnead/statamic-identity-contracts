# Statamic Identity Contracts

Identity foundation for the Statamic addon family. It answers one question in a
stable way — **who did this?** — so that addons never have to depend on a
concrete `App\Models\User`.

This package owns no data: no migrations, no models, no Control Panel screens.
It ships a value object, four contracts and inert defaults.

## This is a library, not an addon

Despite the name, there is nothing Statamic-specific in here. It is a plain
Laravel package: it does not require `statamic/cms`, references no Statamic
class, registers no Control Panel screen and adds nothing a site owner can see
or click. It is not listed on the Statamic Marketplace and should not be.

Install it if you are **building** an addon or an application that needs to
record or notify an actor without reaching for the host application's user
model. If you are running a Statamic site, you will get this package pulled in
as a dependency of something else, and you never need to think about it.

## Requirements

| | |
| --- | --- |
| PHP | 8.2+ (8.3+ when running Laravel 13) |
| Laravel | 12.x or 13.x |
| Statamic | not required, and not used |

Laravel 11 is **not** supported: every `laravel/framework` v11 release is
covered by security advisories, so Composer declines to install the line under
its default policy.

## Why it exists

Addon extraction has a recurring blocker: an addon needs to record or notify an
actor, and reaches for the host application's user model. That single reference
makes the addon unshippable. `Identity` breaks the dependency in both
directions — the addon asks for an identity, the application decides what one is.

## Install

```bash
composer require goldnead/statamic-identity-contracts
```

Nothing to configure for the default behaviour. Publish the config if you need
to change it:

```bash
php artisan vendor:publish --tag=identity-contracts-config
```

## The `Identity` value object

A readonly bag of scalars, safe to persist and to put on a queue:

| Field | Meaning |
| --- | --- |
| `type` | `user`, `contact`, `system`, `anonymous`, or an app-defined type |
| `id` | stringified identifier within that type |
| `userId` | join key into the host application's user table |
| `contactUuid` | join key into the CRM contact record |
| `email`, `name` | convenience copies, personal data |
| `anonymousId` | pseudonymous visitor id for pre-identification activity |
| `meta` | free-form, application-defined |

```php
use Goldnead\IdentityContracts\Identity;

Identity::user(42, 'a@example.com', 'Adrian');
Identity::contact('c-uuid', 'a@example.com');
Identity::system('importer');
Identity::anonymous('anon-1');
```

Copies rather than mutation: `withContactUuid()`, `withEmail()`,
`withAnonymousId()`, `withMeta()`.

`pseudonymised()` drops `email`, `name` and `meta` while keeping the join keys —
this is what consumers call to honour retention rules without losing the ability
to count what happened.

`toArray()` / `fromArray()` round-trip losslessly; the array keys are snake_case
and match the column names a persisting consumer will want.

## Resolving

```php
use Goldnead\IdentityContracts\Facades\IdentityContext;

IdentityContext::current();                 // actor behind this execution context
IdentityContext::resolve($user);            // any subject → Identity
IdentityContext::actingAs($actor, fn () => …); // pin an actor for a job or import
```

`resolve()` accepts an `Identity`, anything implementing `ProvidesIdentity`, any
`Authenticatable`, or an email string. Resolution order:

1. registered custom resolvers (last registered wins)
2. `ProvidesIdentity::toIdentity()`
3. `Authenticatable` → `Identity::user(...)`, enriched with the CRM join key
4. email string → contact lookup, else a contact-shaped identity without a uuid
5. fallback

**Unrecognised subjects never throw.** Identity is metadata; a ledger write must
not fail because an actor could not be classified. The fallback is
`Identity::anonymous()` in HTTP and `Identity::system()` in the console.

`current()` returns the `actingAs` identity if one is set, otherwise the
authenticated user, otherwise the fallback. It never returns `null` — "nobody in
particular" is itself an identity.

## Extension points

**`ProvidesIdentity`** — implement on your User model to control its
representation completely:

```php
class User extends Authenticatable implements ProvidesIdentity
{
    public function toIdentity(): Identity
    {
        return Identity::user($this->id, $this->email, $this->name, $this->contact_uuid);
    }
}
```

**`IdentityResolver`** — teach the manager about a subject type it has never
seen:

```php
IdentityContext::resolveUsing(fn ($subject) => $subject instanceof ApiClient
    ? Identity::system('api:'.$subject->handle)
    : null);
```

**`ContactLocator`** — bridges an email to a CRM contact. Default binding is a
no-op, so any package may ask for a contact uuid without requiring a CRM to
exist. Applications running LeadHub bind an implementation reading
`leadhub_contacts`; that is the only place the join by email lives.

**`AnonymousIdResolver`** — supplies the pseudonymous visitor id. The bundled
`SessionAnonymousIdResolver` stores a uuid in the existing session and
deliberately sets **no cookie of its own**, so no additional consent surface is
created. With `anonymous.persist` off it returns a one-way hash of the session id
and writes nothing. With `anonymous.enabled` off it returns `null` forever.

## Configuration

```php
'resolve_from_auth' => true,   // let current() fall back to the auth guard
'system_id' => 'system',       // actor id for schedulers, webhooks, workers
'anonymous' => [
    'enabled' => true,
    'persist' => true,
    'session_key' => 'identity_anonymous_id',
],
```

Headless applications that always pass the actor explicitly (API hubs, import
pipelines) should set `resolve_from_auth` to `false`.

## Privacy notes

- Nothing is persisted by this package.
- An email address is never reused as an identifier — a contact without a uuid
  keeps `id` as `null`.
- `pseudonymised()` exists so consumers can retain behavioural records after a
  deletion request without keeping personal data.

## Tests

```bash
composer install
composer test      # Pest
composer lint      # Pint, check only
composer analyse   # PHPStan level 8
```

## Support

Only the latest version is supported. Bugs and questions go to
[GitHub issues](https://github.com/goldnead/statamic-identity-contracts/issues);
security reports go to the private channel named in
[SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE).
