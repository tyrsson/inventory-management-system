<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Admin\RequestHandler\ResourceListHandler;

final class ResourceListHandlerFactory
{
    public function __invoke(ContainerInterface $container): ResourceListHandler
    {
        $config = $container->get('config');

        return new ResourceListHandler(
            $config[AclInterface::class] ?? [],
            $container->get(TemplateRendererInterface::class),
        );
    }
}
