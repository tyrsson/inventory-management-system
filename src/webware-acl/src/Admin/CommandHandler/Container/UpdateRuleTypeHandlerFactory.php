<?php

declare(strict_types=1);

/**
 * This file is part of the Webware\Acl package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Acl\Admin\CommandHandler\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\CommandHandler\UpdateRuleTypeHandler;
use Webware\Acl\Repository\RoleRepository;
use Webware\Acl\Repository\RuleRepository;

final class UpdateRuleTypeHandlerFactory
{
    public function __invoke(ContainerInterface $container): UpdateRuleTypeHandler
    {
        return new UpdateRuleTypeHandler(
            $container->get(RuleRepository::class),
            $container->get(RoleRepository::class),
        );
    }
}
