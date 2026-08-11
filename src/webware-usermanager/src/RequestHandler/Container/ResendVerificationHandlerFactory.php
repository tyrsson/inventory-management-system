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

namespace Webware\UserManager\RequestHandler\Container;

use Axleus\Mailer\MailerInterface;
use Laminas\View\HelperPluginManager;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\RequestHandler\ResendVerificationHandler;
use Webware\UserManager\View\Helper\UserUrl;

final class ResendVerificationHandlerFactory
{
    public function __invoke(ContainerInterface $container): ResendVerificationHandler
    {
        /** @var array{user: array{from_email: string, from_name: string, base_url: string}, MailerInterface::class: array{verification_email_subject: string}} $config */
        $config     = $container->get('config');
        $userConf   = $config['user'] ?? [];
        $mailerConf = $config[MailerInterface::class] ?? [];

        /** @var HelperPluginManager $helperManager */
        $helperManager = $container->get(HelperPluginManager::class);
        $userUrl       = $helperManager->get(UserUrl::class);

        return new ResendVerificationHandler(
            template: $container->get(TemplateRendererInterface::class),
            users: $container->get(UserRepositoryInterface::class),
            mailer: $container->get(MailerInterface::class),
            fromEmail: (string) ($userConf['from_email'] ?? 'noreply@farmers-ims.local'),
            fromName: (string) ($userConf['from_name'] ?? 'Farmers IMS'),
            baseUrl: (string) ($userConf['base_url'] ?? 'http://localhost:8080'),
            verificationSubject: (string) ($mailerConf['verification_email_subject'] ?? 'Verify your account'),
            loginUrl: $userUrl('session.read'),
            userUrl: $userUrl,
        );
    }
}
