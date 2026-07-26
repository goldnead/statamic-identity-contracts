<?php

use Goldnead\IdentityContracts\Contracts\ContactLocator;
use Goldnead\IdentityContracts\Facades\IdentityContext;
use Goldnead\IdentityContracts\Identity;
use Goldnead\IdentityContracts\Tests\Fixtures\FixtureUser;
use Goldnead\IdentityContracts\Tests\Fixtures\SelfDescribingUser;

it('resolves an authenticatable without knowing the model', function (): void {
    $user = new FixtureUser(['id' => 5, 'email' => 'a@example.com', 'name' => 'Adrian']);

    $identity = IdentityContext::resolve($user);

    expect($identity->type)->toBe(Identity::TYPE_USER)
        ->and($identity->userId)->toBe('5')
        ->and($identity->email)->toBe('a@example.com')
        ->and($identity->name)->toBe('Adrian');
});

it('lets a model describe itself via ProvidesIdentity', function (): void {
    $user = new SelfDescribingUser([
        'id' => 8,
        'email' => 'b@example.com',
        'name' => 'Bea',
        'contact_uuid' => 'c-8',
        'role' => 'student',
    ]);

    $identity = IdentityContext::resolve($user);

    expect($identity->contactUuid)->toBe('c-8')
        ->and($identity->meta)->toBe(['role' => 'student']);
});

it('passes an identity through untouched', function (): void {
    $identity = Identity::system('importer');

    expect(IdentityContext::resolve($identity))->toBe($identity);
});

it('resolves an email string to a contact-shaped identity without a uuid', function (): void {
    $identity = IdentityContext::resolve('someone@example.com');

    expect($identity->type)->toBe(Identity::TYPE_CONTACT)
        ->and($identity->email)->toBe('someone@example.com')
        ->and($identity->contactUuid)->toBeNull()
        ->and($identity->id)->toBeNull();
});

it('uses a bound contact locator to fill in the crm join key', function (): void {
    app()->bind(ContactLocator::class, fn () => new class implements ContactLocator
    {
        public function locateByEmail(string $email): ?Identity
        {
            return $email === 'known@example.com' ? Identity::contact('c-known', $email) : null;
        }

        public function locateByUuid(string $uuid): ?Identity
        {
            return null;
        }
    });

    expect(IdentityContext::resolve('known@example.com')->contactUuid)->toBe('c-known')
        ->and(IdentityContext::resolve('unknown@example.com')->contactUuid)->toBeNull();

    // and it enriches a user identity too
    $user = new FixtureUser(['id' => 3, 'email' => 'known@example.com']);
    expect(IdentityContext::resolve($user)->contactUuid)->toBe('c-known');
});

it('prefers a custom resolver over the bundled behaviour', function (): void {
    IdentityContext::resolveUsing(fn (mixed $subject) => $subject instanceof FixtureUser
        ? Identity::system('replaced')
        : null);

    expect(IdentityContext::resolve(new FixtureUser(['id' => 1]))->type)->toBe(Identity::TYPE_SYSTEM)
        ->and(IdentityContext::resolve(new FixtureUser(['id' => 1]))->id)->toBe('replaced');
});

it('degrades an unrecognised subject to the fallback instead of throwing', function (): void {
    expect(IdentityContext::resolve(new stdClass)->isSystem())->toBeTrue();
});

it('falls back to the system actor in the console', function (): void {
    expect(app()->runningInConsole())->toBeTrue()
        ->and(IdentityContext::current()->type)->toBe(Identity::TYPE_SYSTEM)
        ->and(IdentityContext::current()->id)->toBe('system');
});

it('pins an actor for the duration of actingAs and restores it afterwards', function (): void {
    $actor = Identity::user(11, 'c@example.com');

    $inside = IdentityContext::actingAs($actor, fn () => IdentityContext::current());

    expect($inside)->toBe($actor)
        ->and(IdentityContext::current()->isSystem())->toBeTrue();
});

it('restores the previous actor even when the callback throws', function (): void {
    try {
        IdentityContext::actingAs(Identity::user(1), function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(IdentityContext::current()->isSystem())->toBeTrue();
});

it('nests actingAs correctly', function (): void {
    $outer = Identity::user(1);
    $inner = Identity::user(2);

    $seen = IdentityContext::actingAs($outer, fn () => [
        IdentityContext::current(),
        IdentityContext::actingAs($inner, fn () => IdentityContext::current()),
        IdentityContext::current(),
    ]);

    expect($seen[0])->toBe($outer)
        ->and($seen[1])->toBe($inner)
        ->and($seen[2])->toBe($outer);
});

it('resolves the authenticated user when the guard has one', function (): void {
    $user = new FixtureUser(['id' => 21, 'email' => 'd@example.com', 'name' => 'Dana']);
    $this->be($user);

    expect(IdentityContext::current()->userId)->toBe('21');
});

it('ignores the auth guard when resolve_from_auth is off', function (): void {
    config()->set('identity-contracts.resolve_from_auth', false);
    $this->be(new FixtureUser(['id' => 22]));

    expect(IdentityContext::current()->isSystem())->toBeTrue();
});
