<?php

namespace Goldnead\IdentityContracts;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A stable, transport-safe description of *who* did something.
 *
 * This is deliberately a value object of plain scalars, not a wrapper around a
 * user model: consuming addons persist these fields (an activity ledger, a
 * notification, an entitlement grant) long after the underlying record may have
 * been renamed, merged or deleted. Nothing here resolves lazily.
 */
final readonly class Identity implements JsonSerializable
{
    public const TYPE_USER = 'user';

    public const TYPE_CONTACT = 'contact';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_ANONYMOUS = 'anonymous';

    /**
     * @param  string  $type  Actor category: user, contact, system, anonymous or an app-defined type.
     * @param  string|null  $id  Stringified identifier within that type.
     * @param  string|null  $userId  Join key into the host application's user table, when known.
     * @param  string|null  $contactUuid  Join key into the CRM contact record, when known.
     * @param  string|null  $anonymousId  Pseudonymous visitor id for pre-identification activity.
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $userId = null,
        public ?string $contactUuid = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $anonymousId = null,
        public array $meta = [],
    ) {
        if ($this->type === '') {
            throw new InvalidArgumentException('Identity type must not be empty.');
        }
    }

    /** @param  array<string, mixed>  $meta */
    public static function user(int|string $id, ?string $email = null, ?string $name = null, ?string $contactUuid = null, array $meta = []): self
    {
        return new self(
            type: self::TYPE_USER,
            id: (string) $id,
            userId: (string) $id,
            contactUuid: $contactUuid,
            email: $email,
            name: $name,
            meta: $meta,
        );
    }

    /** @param  array<string, mixed>  $meta */
    public static function contact(string $uuid, ?string $email = null, ?string $name = null, array $meta = []): self
    {
        return new self(
            type: self::TYPE_CONTACT,
            id: $uuid,
            contactUuid: $uuid,
            email: $email,
            name: $name,
            meta: $meta,
        );
    }

    /**
     * The application itself acting without a human actor: webhooks, schedulers,
     * queue workers, imports.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function system(?string $id = null, array $meta = []): self
    {
        return new self(
            type: self::TYPE_SYSTEM,
            id: $id ?? (string) config('identity-contracts.system_id', 'system'),
            meta: $meta,
        );
    }

    /** @param  array<string, mixed>  $meta */
    public static function anonymous(?string $anonymousId = null, array $meta = []): self
    {
        return new self(
            type: self::TYPE_ANONYMOUS,
            id: $anonymousId,
            anonymousId: $anonymousId,
            meta: $meta,
        );
    }

    public function isUser(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isContact(): bool
    {
        return $this->type === self::TYPE_CONTACT;
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }

    public function isAnonymous(): bool
    {
        return $this->type === self::TYPE_ANONYMOUS;
    }

    /** Whether this identity points at a durable record rather than a pseudonym. */
    public function isIdentified(): bool
    {
        return $this->userId !== null || $this->contactUuid !== null;
    }

    // The copy-on-write modifiers below all spell out every constructor argument
    // by name. That is verbose on purpose: with positional arguments, inserting a
    // constructor parameter would silently shift every later value into the wrong
    // field, and the type system would not catch it because six of the eight
    // parameters are `?string`.

    /** Returns a copy with the CRM join key filled in. */
    public function withContactUuid(?string $uuid): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            userId: $this->userId,
            contactUuid: $uuid,
            email: $this->email,
            name: $this->name,
            anonymousId: $this->anonymousId,
            meta: $this->meta,
        );
    }

    public function withEmail(?string $email): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            userId: $this->userId,
            contactUuid: $this->contactUuid,
            email: $email,
            name: $this->name,
            anonymousId: $this->anonymousId,
            meta: $this->meta,
        );
    }

    public function withAnonymousId(?string $anonymousId): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            userId: $this->userId,
            contactUuid: $this->contactUuid,
            email: $this->email,
            name: $this->name,
            anonymousId: $anonymousId,
            meta: $this->meta,
        );
    }

    /**
     * Merges into the existing meta rather than replacing it.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            userId: $this->userId,
            contactUuid: $this->contactUuid,
            email: $this->email,
            name: $this->name,
            anonymousId: $this->anonymousId,
            meta: [...$this->meta, ...$meta],
        );
    }

    /**
     * Drops every directly personal field, keeping only the pseudonymous join
     * keys. Consumers use this to honour retention and anonymisation rules
     * without losing the ability to count what happened.
     */
    public function pseudonymised(): self
    {
        return new self(
            type: $this->type,
            id: $this->id,
            userId: $this->userId,
            contactUuid: $this->contactUuid,
            email: null,
            name: null,
            anonymousId: $this->anonymousId,
            meta: [],
        );
    }

    public function equals(?self $other): bool
    {
        return $other !== null
            && $other->type === $this->type
            && $other->id === $this->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'user_id' => $this->userId,
            'contact_uuid' => $this->contactUuid,
            'email' => $this->email,
            'name' => $this->name,
            'anonymous_id' => $this->anonymousId,
            'meta' => $this->meta,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? self::TYPE_ANONYMOUS),
            id: isset($data['id']) ? (string) $data['id'] : null,
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            contactUuid: isset($data['contact_uuid']) ? (string) $data['contact_uuid'] : null,
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            anonymousId: isset($data['anonymous_id']) ? (string) $data['anonymous_id'] : null,
            meta: (array) ($data['meta'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
