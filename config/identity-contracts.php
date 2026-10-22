<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resolve from the auth guard
    |--------------------------------------------------------------------------
    |
    | When enabled, IdentityContext::current() falls back to the authenticated
    | user of the default guard. Turn this off in headless applications that
    | always pass the actor explicitly (API hubs, import pipelines).
    |
    */

    'resolve_from_auth' => env('IDENTITY_RESOLVE_FROM_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | System actor id
    |--------------------------------------------------------------------------
    |
    | Written as the actor for anything happening without a human: schedulers,
    | webhooks, queue workers, imports.
    |
    */

    'system_id' => env('IDENTITY_SYSTEM_ID', 'system'),

    /*
    |--------------------------------------------------------------------------
    | Anonymous visitor id
    |--------------------------------------------------------------------------
    |
    | Pseudonymous id for activity that happens before someone identifies
    | themselves. `persist` stores a uuid in the existing session; with it
    | disabled a one-way hash of the session id is used and nothing is written.
    | Set `enabled` to false to never emit an anonymous id at all.
    |
    */

    'anonymous' => [
        'enabled' => env('IDENTITY_ANONYMOUS_ENABLED', true),
        'persist' => env('IDENTITY_ANONYMOUS_PERSIST', true),
        'session_key' => 'identity_anonymous_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto login links
    |--------------------------------------------------------------------------
    |
    | Links in a mail to a page behind the login become signed, expiring links
    | that log in exactly the recipient and then redirect to the original path.
    | Addons that render mail (marketing, lead magnets) ask AutoLogin to rewrite
    | their links; this block says whether and for whom that happens.
    |
    | enabled        Off by default. Nothing is rewritten and the route answers 404.
    | route          Path of the endpoint. The signature is bound to it.
    | paths          Only internal paths starting with one of these are rewritten
    |                and only they are accepted when a link is used. Empty = nothing.
    | hosts          Hosts that count as internal, besides the host of app.url.
    | ttl_days       How long a link works.
    | allowed_roles  null = no role check. A list = only users whose role attribute
    |                is in it (an empty string stands for "no role"). CP access and
    |                super users are always refused, whatever this says.
    | role_attribute The user attribute holding the role.
    | eligible       Optional callable(Authenticatable): bool. The host's veto.
    | verify_email   Mark email_verified_at on the first login through a link. Only
    |                switch on where "clicked a link in their own mailbox" is what
    |                verified means.
    | guard          Auth guard to log in with; null = the default guard.
    | rate_limit     Requests per minute and IP for the endpoint.
    | renew_limit    New-link mails per account and hour.
    | login_url      Where the expired page sends people who prefer to log in.
    |
    */

    'autologin' => [
        'enabled' => env('IDENTITY_AUTOLOGIN_ENABLED', false),
        'route' => '!/autologin',
        'paths' => [],
        'hosts' => [],
        'ttl_days' => 14,
        'allowed_roles' => null,
        'role_attribute' => 'role',
        'eligible' => null,
        'verify_email' => false,
        'guard' => null,
        'rate_limit' => 30,
        'renew_limit' => 3,
        'login_url' => '/login',
    ],

];
