<?php

declare(strict_types=1);

namespace Ims\Store\Http\Container;

use Ims\Store\Http\Handler\Admin\UpdateUserHandler;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class UpdateUserHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): UpdateUserHandler
    {
        return new UpdateUserHandler(
            $container->get(TemplateRendererInterface::class),
            $container->get(UserRepositoryInterface::class),
        );
    }
}
