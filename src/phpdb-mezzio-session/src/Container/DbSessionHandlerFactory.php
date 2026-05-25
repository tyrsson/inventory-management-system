<?php

declare(strict_types=1);


namespace PhpDb\Session\Container;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Session\DbSessionHandler;
use Psr\Container\ContainerInterface;

final class DbSessionHandlerFactory
{
    public function __invoke(ContainerInterface $container): DbSessionHandler
    {
        return new DbSessionHandler(
            $container->get(AdapterInterface::class),
        );
    }
}
