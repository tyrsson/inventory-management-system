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
use Webware\UserManager\RequestHandler\RegistrationHandler;
use Webware\UserManager\View\Helper\UserUrl;

final class RegistrationHandlerFactory
{
    public function __invoke(ContainerInterface $container): RegistrationHandler
    {
        /** @var HelperPluginManager $helperManager */
        $helperManager = $container->get(HelperPluginManager::class);
        $userUrl       = $helperManager->get(UserUrl::class);

        return new RegistrationHandler(
            $container->get(TemplateRendererInterface::class),
            loginUrl: $userUrl('session.read'),
        );
    }
}
