<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\RequestHandler\EditRoleModalHandler;
use Webware\Acl\Repository\RoleRepository;

final class EditRoleModalHandlerFactory
{
    public function __invoke(ContainerInterface $container): EditRoleModalHandler
    {
        return new EditRoleModalHandler(
            $container->get(TemplateRendererInterface::class),
            $container->get(RoleRepository::class),
        );
    }
}
