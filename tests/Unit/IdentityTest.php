<?php

use Goldnead\IdentityContracts\Identity;

it('builds a user identity that carries the user join key', function (): void {
    $identity = Identity::user(42, 'a@example.com', 'Adrian');

    expect($identity->type)->toBe(Identity::TYPE_USER)
        ->and($identity->id)->toBe('42')
        ->and($identity->userId)->toBe('42')
        ->and($identity->isUser())->toBeTrue()
        ->and($identity->isIdentified())->toBeTrue();
});

it('builds a contact identity that carries the crm join key', function (): void {
    $identity = Identity::contact('c-uuid-1', 'a@example.com');

    expect($identity->contactUuid)->toBe('c-uuid-1')
        ->and($identity->isContact())->toBeTrue()
        ->and($identity->isIdentified())->toBeTrue();
});

it('treats an anonymous identity as unidentified', function (): void {
    $identity = Identity::anonymous('anon-1');

    expect($identity->anonymousId)->toBe('anon-1')
        ->and($identity->isAnonymous())->toBeTrue()
        ->and($identity->isIdentified())->toBeFalse();
});

it('rejects an empty type', function (): void {
    new Identity(type: '');
})->throws(InvalidArgumentException::class);

it('returns modified copies instead of mutating', function (): void {
    $original = Identity::user(1, 'a@example.com');
    $enriched = $original->withContactUuid('c-1')->withMeta(['role' => 'student']);

    expect($original->contactUuid)->toBeNull()
        ->and($original->meta)->toBe([])
        ->and($enriched->contactUuid)->toBe('c-1')
        ->and($enriched->meta)->toBe(['role' => 'student'])
        ->and($enriched->userId)->toBe('1');
});

it('strips direct personal data but keeps the join keys when pseudonymised', function (): void {
    $identity = Identity::user(7, 'a@example.com', 'Adrian', 'c-7')->withMeta(['ip' => '1.2.3.4']);

    $safe = $identity->pseudonymised();

    expect($safe->email)->toBeNull()
        ->and($safe->name)->toBeNull()
        ->and($safe->meta)->toBe([])
        ->and($safe->userId)->toBe('7')
        ->and($safe->contactUuid)->toBe('c-7')
        ->and($safe->type)->toBe(Identity::TYPE_USER);
});

it('survives an array round trip', function (): void {
    $identity = Identity::user(9, 'a@example.com', 'Adrian', 'c-9', ['source' => 'import', 'run' => 3])
        ->withAnonymousId('anon-9');

    expect(Identity::fromArray($identity->toArray()))->toEqual($identity);
});

it('compares by id when both sides carry one', function (): void {
    expect(Identity::user(1, 'a@example.com')->equals(Identity::user(1, 'renamed@example.com')))->toBeTrue()
        ->and(Identity::user(1)->equals(Identity::user(2)))->toBeFalse()
        ->and(Identity::user(1)->equals(Identity::contact('1')))->toBeFalse()
        ->and(Identity::user(1)->equals(null))->toBeFalse();
});

it('never claims two unidentified identities are the same actor', function (): void {
    // The manager builds exactly this shape for an email string when no
    // ContactLocator is bound, which is the default. Before the fail-closed
    // rewrite both of these compared equal, because type and id matched on null.
    $alice = new Identity(type: Identity::TYPE_CONTACT, email: 'alice@example.com');
    $bob = new Identity(type: Identity::TYPE_CONTACT, email: 'bob@example.com');

    expect($alice->equals($bob))->toBeFalse()
        ->and(Identity::anonymous()->equals(Identity::anonymous()))->toBeFalse();
});

it('accepts a join key as proof that two identities are the same actor', function (): void {
    $fromLedger = (new Identity(type: Identity::TYPE_CONTACT))->withContactUuid('c-1');
    $fromForm = (new Identity(type: Identity::TYPE_CONTACT))->withContactUuid('c-1');

    expect($fromLedger->equals($fromForm))->toBeTrue()
        ->and($fromLedger->equals($fromForm->withEmail('a@example.com')))->toBeTrue();
});

it('rejects a match when another shared field contradicts the join key', function (): void {
    $one = (new Identity(type: Identity::TYPE_CONTACT))->withContactUuid('c-1')->withEmail('a@example.com');
    $two = (new Identity(type: Identity::TYPE_CONTACT))->withContactUuid('c-1')->withEmail('b@example.com');

    expect($one->equals($two))->toBeFalse();
});
