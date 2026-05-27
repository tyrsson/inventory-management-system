<?php

declare(strict_types=1);

use Webware\UserManager\Event\SendVerificationEmailEvent;
use Webware\UserManager\Listener\SendVerificationEmailListener;

return [
    'listeners' => [
        SendVerificationEmailEvent::class => [
            ['listener' => SendVerificationEmailListener::class, 'priority' => 1],
        ],
    ],
    'user' => [
        /**
         * How long (in seconds) a verification token remains valid.
         * Default: 86400 (24 hours).
         */
        'verification_token_ttl' => 86400,
    ],
];
