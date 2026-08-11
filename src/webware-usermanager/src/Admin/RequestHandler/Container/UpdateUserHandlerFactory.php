<?php

declare(strict_types=1);

namespace Webware\UserManager\Admin\RequestHandler\Container;

use Mezzio\Template\TemplateRendererInterface;
use Psl\Type;
use Psr\Container\ContainerInterface;
use Webware\UserManager\Admin\RequestHandler\UpdateUserHandler;

final class UpdateUserHandlerFactory
{
    public function __invoke(ContainerInterface $container): UpdateUserHandler
    {
        $templateRenderer = $container->get(TemplateRendererInterface::class);
        Type\instance_of(TemplateRendererInterface::class)->assert($templateRenderer);
        return new UpdateUserHandler($templateRenderer);
    }
}
