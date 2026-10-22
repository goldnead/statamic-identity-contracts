<?php

return [
    'expired' => [
        'title' => 'This link has expired',
        'text' => 'For security these links only work for a while. We can send a new link to your address, and one click gets you back in.',
        'renew' => 'Email me a new link',
        'login' => 'Or log in',
    ],
    'outdated' => [
        'title' => 'This link is no longer valid',
        'text' => 'Something about your account has changed since this link was sent, for example the password. Please log in the usual way.',
        'login' => 'Go to login',
    ],
    'renewed' => [
        'title' => 'Your new link is on its way',
        'text' => 'We have emailed you a new link. Check your spam folder if it does not show up right away.',
    ],
    'switch' => [
        'title' => 'You are logged in with another account',
        'text' => 'You are currently logged in as :current. This link belongs to :target. Do you want to switch?',
        'confirm' => 'Switch to :target',
        'stay' => 'No, stay logged in',
    ],
    'mail' => [
        'subject' => 'Your new link',
        'greeting' => 'Hello,',
        'text' => 'here is your new link. One click and you are in.',
        'button' => 'Open now',
        'ignore' => 'If you did not ask for this link, you can ignore this email.',
    ],
];
