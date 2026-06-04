<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Webware\Acl\Admin\RequestHandler\DeleteRuleModalHandler;

final class DeleteRuleModalHandlerFactory
{
    public function __invoke(ContainerInterface $container): DeleteRuleModalHandler
    {
        return new DeleteRuleModalHandler(
            $container->get(TemplateRendererInterface::class),
        );
    }
}
