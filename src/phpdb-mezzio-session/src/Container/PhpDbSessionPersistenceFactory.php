<?php

declare(strict_types=1);


namespace PhpDb\Session\Container;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Session\PhpDbSessionPersistence;
use Psr\Container\ContainerInterface;

final class PhpDbSessionPersistenceFactory
{
    public function __invoke(ContainerInterface $container): PhpDbSessionPersistence
    {
        return new PhpDbSessionPersistence(
            $container->get(AdapterInterface::class),
        );
    }
}
