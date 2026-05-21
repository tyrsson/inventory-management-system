<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\RequestHandler\RuleManagerHandler;
use Webware\Acl\AclInterface;

final class RuleManagerHandlerFactory
{
    public function __invoke(ContainerInterface $container): RuleManagerHandler
    {
        $config = $container->get('config');

        return new RuleManagerHandler(
            $config[AclInterface::class] ?? [],
            $container->get(TemplateRendererInterface::class),
        );
    }
}
