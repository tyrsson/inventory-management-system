<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\RequestHandler\AclOverviewHandler;

final class AclOverviewHandlerFactory
{
    public function __invoke(ContainerInterface $container): AclOverviewHandler
    {
        return new AclOverviewHandler(
            $container->get(TemplateRendererInterface::class),
        );
    }
}
