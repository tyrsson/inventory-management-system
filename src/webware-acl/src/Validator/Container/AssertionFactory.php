<?php

declare(strict_types=1);

namespace Webware\Acl\Validator\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AssertionManager;
use Webware\Acl\Validator\Assertion;

final readonly class AssertionFactory
{
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null
    ): Assertion {
        return new Assertion(
            $container->get(AssertionManager::class),
            $options
        );
    }
}
