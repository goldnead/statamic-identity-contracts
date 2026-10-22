<?php

namespace Goldnead\IdentityContracts\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The mail an expired link's page sends on request: one fresh link, nothing else.
 */
class AutoLoginRenewedMail extends Mailable
{
    public function __construct(public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('identity-contracts::autologin.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'identity-contracts::autologin.mail',
            text: 'identity-contracts::autologin.mail-text',
        );
    }
}
