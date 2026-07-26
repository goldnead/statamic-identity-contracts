<?php

namespace Goldnead\IdentityContracts;

use Closure;
use Goldnead\IdentityContracts\Contracts\AnonymousIdResolver;
use Goldnead\IdentityContracts\Contracts\ContactLocator;
use Goldnead\IdentityContracts\Contracts\IdentityResolver;
use Goldnead\IdentityContracts\Contracts\ProvidesIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityManager
{
    /** @var array<int, IdentityResolver|Closure> */
    protected array $resolvers = [];

    /** An identity forced by actingAs(), taking precedence over the auth guard. */
    protected ?Identity $impersonated = null;

    /**
     * Register a custom resolver. Later registrations win, so an application can
     * always override a bundled behaviour without unregistering anything.
     */
    public function resolveUsing(IdentityResolver|Closure $resolver): static
    {
        array_unshift($this->resolvers, $resolver);

        return $this;
    }

    /**
     * The actor behind the current execution context: an explicitly set identity,
     * the authenticated user, or an anonymous/system fallback. Never returns null
     * — "nobody in particular" is itself an identity.
     */
    public function current(): Identity
    {
        if ($this->impersonated !== null) {
            return $this->impersonated;
        }

        if (config('identity-contracts.resolve_from_auth', true)) {
            $user = $this->authenticatedUser();

            if ($user !== null) {
                return $this->resolve($user);
            }
        }

        return $this->fallback();
    }

    /**
     * Resolve any subject to an Identity. Unrecognised subjects degrade to the
     * anonymous/system fallback rather than throwing: identity is metadata, and
     * a ledger write must never fail because an actor could not be classified.
     */
    public function resolve(mixed $subject): Identity
    {
        if ($subject instanceof Identity) {
            return $subject;
        }

        if ($subject === null) {
            return $this->current();
        }

        foreach ($this->resolvers as $resolver) {
            $identity = $resolver instanceof IdentityResolver
                ? $resolver->resolve($subject)
                : $resolver($subject);

            if ($identity instanceof Identity) {
                return $identity;
            }
        }

        if ($subject instanceof ProvidesIdentity) {
            return $subject->toIdentity();
        }

        if ($subject instanceof Authenticatable) {
            return $this->fromAuthenticatable($subject);
        }

        if (is_string($subject) && filter_var($subject, FILTER_VALIDATE_EMAIL)) {
            // No CRM record (or no locator bound): still a contact-shaped actor,
            // but without a uuid. The email is never reused as an identifier.
            return $this->locateContact($subject)
                ?? new Identity(type: Identity::TYPE_CONTACT, email: $subject);
        }

        return $this->fallback();
    }

    /**
     * Run a callback with a fixed actor. Used by queue jobs and imports, which
     * run outside the request that caused them and would otherwise be attributed
     * to nobody.
     */
    public function actingAs(mixed $subject, Closure $callback): mixed
    {
        $previous = $this->impersonated;
        $this->impersonated = $subject instanceof Identity ? $subject : $this->resolve($subject);

        try {
            return $callback();
        } finally {
            $this->impersonated = $previous;
        }
    }

    public function setCurrent(mixed $subject): static
    {
        $this->impersonated = $subject === null ? null : $this->resolve($subject);

        return $this;
    }

    public function forget(): static
    {
        $this->impersonated = null;
        $this->resolvers = [];

        return $this;
    }

    public function system(?string $id = null): Identity
    {
        return Identity::system($id);
    }

    /** Ask the bound ContactLocator for a CRM record; null when none is bound. */
    public function locateContact(string $email): ?Identity
    {
        return app(ContactLocator::class)->locateByEmail($email);
    }

    /**
     * Enrich an identity with the CRM join key, so downstream consumers can
     * correlate a user action with the marketing contact without doing the email
     * lookup themselves.
     */
    public function withContact(Identity $identity): Identity
    {
        if ($identity->contactUuid !== null || $identity->email === null) {
            return $identity;
        }

        $contact = $this->locateContact($identity->email);

        return $contact === null ? $identity : $identity->withContactUuid($contact->contactUuid);
    }

    public function anonymousId(): ?string
    {
        return app(AnonymousIdResolver::class)->resolve();
    }

    /**
     * No human actor could be determined. In an HTTP context that is an
     * anonymous visitor; in the console it is the system itself.
     */
    protected function fallback(): Identity
    {
        if (app()->runningInConsole()) {
            return Identity::system();
        }

        return Identity::anonymous($this->anonymousId());
    }

    protected function authenticatedUser(): ?Authenticatable
    {
        if (! app()->bound('auth')) {
            return null;
        }

        return app('auth')->guard()->user();
    }

    protected function fromAuthenticatable(Authenticatable $user): Identity
    {
        $identity = Identity::user(
            id: $user->getAuthIdentifier(),
            email: $this->attribute($user, 'email'),
            name: $this->attribute($user, 'name'),
        );

        return $this->withContact($identity);
    }

    protected function attribute(object $subject, string $key): ?string
    {
        $value = data_get($subject, $key);

        return is_scalar($value) ? (string) $value : null;
    }
}
