<?php

declare(strict_types=1);

namespace PhpDb\Session\Container;

use ArrayAccess;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Session\PhpDbSessionPersistence;
use Psr\Container\ContainerInterface;

use function assert;
use function is_array;

final class PhpDbSessionPersistenceFactory
{
    public function __invoke(ContainerInterface $container): PhpDbSessionPersistence
    {
        $config = $container->has('config') ? $container->get('config') : [];
        assert(is_array($config) || $config instanceof ArrayAccess);

        return PhpDbSessionPersistence::fromConfigArray(
            $container->get(AdapterInterface::class),
            isset($config['session']) && is_array($config['session']) ? $config['session'] : []
        );
    }
}
