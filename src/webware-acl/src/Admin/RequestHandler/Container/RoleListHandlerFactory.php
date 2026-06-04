<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Admin\RequestHandler\RoleListHandler;

final class RoleListHandlerFactory
{
    public function __invoke(ContainerInterface $container): RoleListHandler
    {
        $config = $container->get('config');

        return new RoleListHandler(
            $config[AclInterface::class] ?? [],
            $container->get(TemplateRendererInterface::class),
        );
    }
}
