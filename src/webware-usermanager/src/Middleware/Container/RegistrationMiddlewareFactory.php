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

namespace Webware\UserManager\Middleware\Container;

use Laminas\InputFilter\InputFilterPluginManager;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\InputFilter\UserDataFilter;
use Webware\UserManager\Middleware\RegistrationMiddleware;
use Webware\UserManager\UserInterface;

final class RegistrationMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): RegistrationMiddleware
    {
        return new RegistrationMiddleware(
            $container->get(CommandBusInterface::class),
            $container->get(TemplateRendererInterface::class),
            $container->get(InputFilterPluginManager::class)->get(UserDataFilter::class),
        );
    }
}
