<?php

declare(strict_types=1);

namespace Webware\Acl\Listener\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\Listener\RegisterAclRulesListener;

final readonly class RegisterAclRulesListenerFactory
{
    public function __invoke(ContainerInterface $container): RegisterAclRulesListener
    {
        return new RegisterAclRulesListener();
    }
}
