<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\RequestHandler\AddRoleModalHandler;
use Webware\Acl\Repository\RoleRepository;

final class AddRoleModalHandlerFactory
{
    public function __invoke(ContainerInterface $container): AddRoleModalHandler
    {
        return new AddRoleModalHandler(
            $container->get(TemplateRendererInterface::class),
            $container->get(RoleRepository::class),
        );
    }
}
