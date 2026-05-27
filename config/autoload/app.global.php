<?php

declare(strict_types=1);

use Axleus\Mailer\Adapter\MessageInterface;
use Axleus\Mailer\MailerInterface;

return [
    MessageInterface::class => [
        'to'         => 'jsmith@webinertia.net',
        'from'       => 'contact@webinertia.dev',
        'subject'    => 'Webinertia Project Request',
        'auto_reply' => true,
    ],
    MailerInterface::class => [
        'verification_email_subject' => 'Verify your Farmers IMS account',
    ],
    'user' => [
        'from_email' => 'noreply@farmers-ims.local',
        'from_name'  => 'Farmers IMS',
        /**
         * Base URL used when building the email verification link.
         * Override in a local.php file for production.
         */
        'base_url'   => 'http://localhost:8080',
    ],
    'view_helper_config' => [
        'asset' => [
            'resource_map' => [
                'debug.js'     => 'assets/js/debug.js',
                'messenger.js' => 'assets/js/system.messenger.js',
            ],
        ],
    ],
];