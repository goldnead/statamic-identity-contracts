<?php

namespace Goldnead\IdentityContracts\Resolvers;

use Goldnead\IdentityContracts\Contracts\AnonymousIdResolver;
use Illuminate\Support\Str;

/**
 * Derives a pseudonymous visitor id from the existing session. It deliberately
 * does not set a cookie of its own: the session already exists for functional
 * reasons, so no additional consent surface is created. When `persist` is off
 * the id is a one-way hash of the session id and is never written anywhere.
 */
class SessionAnonymousIdResolver implements AnonymousIdResolver
{
    public function resolve(): ?string
    {
        if (! config('identity-contracts.anonymous.enabled', true)) {
            return null;
        }

        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $session = app('session');
        $key = (string) config('identity-contracts.anonymous.session_key', 'identity_anonymous_id');

        if (config('identity-contracts.anonymous.persist', true)) {
            if (! $session->has($key)) {
                $session->put($key, (string) Str::uuid());
            }

            return (string) $session->get($key);
        }

        return hash('sha256', $session->getId());
    }
}
