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

use Laminas\View\HelperPluginManager;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;
use Webware\UserManager\RequestHandler\VerifyEmailHandler;
use Webware\UserManager\View\Helper\UserUrl;

final class VerifyEmailHandlerFactory
{
    public function __invoke(ContainerInterface $container): VerifyEmailHandler
    {
        /** @var array{user: array{verification_token_ttl: int}} $config */
        $config   = $container->get('config');
        $tokenTtl = (int) ($config['user']['verification_token_ttl'] ?? 86400);

        /** @var HelperPluginManager $helperManager */
        $helperManager = $container->get(HelperPluginManager::class);
        $userUrl       = $helperManager->get(UserUrl::class);

        return new VerifyEmailHandler(
            template: $container->get(TemplateRendererInterface::class),
            users: $container->get(UserRepositoryInterface::class),
            tokenTtl: $tokenTtl,
            loginUrl: $userUrl('session.read'),
        );
    }
}
