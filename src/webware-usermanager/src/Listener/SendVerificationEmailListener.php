<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\UserManager\Listener;

use Axleus\Mailer\MailerInterface;
use Webware\UserManager\Event\SendVerificationEmailEvent;

final class SendVerificationEmailListener
{
        public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly string $baseUrl,
        private readonly string $verificationSubject,
    ) {}

    public function __invoke(SendVerificationEmailEvent $event): void
    {
        $command         = $event->getCommand();
        $token           = $event->getToken();
        $verificationUrl = rtrim($this->baseUrl, '/') . '/verify-email/' . $token;

        $adapter = $this->mailer->getAdapter();

        if ($adapter === null) {
            return;
        }

        $adapter
            ->from($this->fromEmail, $this->fromName)
            ->to($event->getEmail())
            ->subject($this->verificationSubject)
            ->isHtml(true)
            ->body(
                '<p>Hello ' . htmlspecialchars($command->firstName, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Thank you for registering. Please verify your email address by clicking the link below.</p>'
                . '<p><a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '">Verify my email</a></p>'
                . '<p>This link expires in 24 hours.</p>'
            )
            ->altBody(
                'Hello ' . $command->firstName . ",\n\n"
                . "Please verify your email address by visiting the following link:\n"
                . $verificationUrl . "\n\n"
                . "This link expires in 24 hours.\n"
            );

        $this->mailer->send();
    }
}
