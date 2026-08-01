# Security Policy

## Supported versions

Only the latest released version receives fixes.

## Reporting a vulnerability

**Do not open a public issue.**

Email <info@adriangoldner.com> with a description of the problem, the version
you tested against, and a reproduction if you have one. You will get an
acknowledgement within a few days.

## Scope note

This package holds no data and performs no network calls. It does carry
identifiers and, when the host application supplies them, email addresses and
names in memory. The likely security-relevant surface is therefore:

- an `IdentityResolver` or `ContactLocator` implementation leaking data across
  tenants or users;
- `Identity::toArray()` output being persisted or logged somewhere it should not
  be — use `pseudonymised()` when you only need the join keys.

Bugs in Laravel itself belong to [laravel/framework](https://github.com/laravel/framework).
