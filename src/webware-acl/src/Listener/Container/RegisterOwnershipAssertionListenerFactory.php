<?php

declare(strict_types=1);

namespace Webware\Acl\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Listener\RegisterOwnershipAssertionListener;

final readonly class RegisterOwnershipAssertionListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterOwnershipAssertionListener
    {
        return new RegisterOwnershipAssertionListener();
    }
}
