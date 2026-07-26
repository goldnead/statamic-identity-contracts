<?php

namespace Goldnead\IdentityContracts\Contracts;

use Goldnead\IdentityContracts\Identity;

/**
 * Bridges an email address to a CRM contact. The default binding is a no-op, so
 * a package may always ask for a contact uuid without requiring a CRM to exist.
 * Applications running LeadHub bind an implementation that reads
 * `leadhub_contacts` — that is the only place the join by email lives.
 */
interface ContactLocator
{
    public function locateByEmail(string $email): ?Identity;

    public function locateByUuid(string $uuid): ?Identity;
}
