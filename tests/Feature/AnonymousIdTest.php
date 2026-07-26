<?php

use Goldnead\IdentityContracts\Contracts\AnonymousIdResolver;
use Goldnead\IdentityContracts\Facades\IdentityContext;

it('returns no anonymous id when there is no started session', function (): void {
    expect(IdentityContext::anonymousId())->toBeNull();
});

it('returns no anonymous id when the feature is disabled', function (): void {
    config()->set('identity-contracts.anonymous.enabled', false);
    session()->start();

    expect(IdentityContext::anonymousId())->toBeNull();
});

it('keeps a stable persisted id across calls in the same session', function (): void {
    session()->start();

    $first = IdentityContext::anonymousId();
    $second = IdentityContext::anonymousId();

    expect($first)->not->toBeNull()
        ->and($first)->toBe($second)
        ->and(session()->get('identity_anonymous_id'))->toBe($first);
});

it('writes nothing to the session when persistence is off', function (): void {
    config()->set('identity-contracts.anonymous.persist', false);
    session()->start();

    $id = IdentityContext::anonymousId();

    expect($id)->toBe(hash('sha256', session()->getId()))
        ->and(session()->has('identity_anonymous_id'))->toBeFalse();
});

it('can be swapped for an application specific resolver', function (): void {
    app()->bind(AnonymousIdResolver::class, fn () => new class implements AnonymousIdResolver
    {
        public function resolve(): ?string
        {
            return 'from-frontend';
        }
    });

    expect(IdentityContext::anonymousId())->toBe('from-frontend');
});
