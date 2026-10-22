<?php

namespace Goldnead\IdentityContracts\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/**
 * The mail an expired link's page sends on request: one fresh link, nothing else.
 *
 * Written out here rather than in a view, because it is five lines, and a host
 * that wants other words overrides the `identity-contracts::autologin.mail`
 * translations.
 */
class AutoLoginRenewedMail extends Mailable
{
    public function __construct(public readonly string $url) {}

    public function build(): static
    {
        $greeting = __('identity-contracts::autologin.mail.greeting');
        $text = __('identity-contracts::autologin.mail.text');
        $button = __('identity-contracts::autologin.mail.button');
        $ignore = __('identity-contracts::autologin.mail.ignore');

        $html = '<!doctype html><html><body style="font-family: Arial, sans-serif; color: #2a2a2a; line-height: 1.5;">'
            .'<p>'.e($greeting).'</p><p>'.e($text).'</p>'
            .'<p><a href="'.e($this->url).'" style="display: inline-block; padding: 12px 20px; background: #2a2a2a; color: #ffffff; text-decoration: none; border-radius: 6px;">'.e($button).'</a></p>'
            .'<p style="color: #666666; font-size: 13px;">'.e($ignore).'</p>'
            .'</body></html>';

        $plain = $greeting."\n\n".$text."\n\n".$this->url."\n\n".$ignore;

        return $this
            ->subject(__('identity-contracts::autologin.mail.subject'))
            ->html($html)
            ->withSymfonyMessage(function (Email $message) use ($plain): void {
                $message->text($plain);
            });
    }
}
