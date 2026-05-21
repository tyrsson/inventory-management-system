<?php

declare(strict_types=1);


namespace Webware\Admin\Listener;

use Webware\Acl\Event\RulesLoadedEvent;
use Webware\Acl\PrivilegeInterface;

/**
 * Registers admin module ACL rules.
 *
 * Grants the generic `administrator` role read access to the admin dashboard
 * resource. IMS-specific role grants (e.g. Warehouse Supervisor) belong in
 * the IMS application layer, not here.
 */
final class RegisterAdminRulesListener
{
    public function __construct(private readonly string $routeNamePrefix) {}

    public function __invoke(RulesLoadedEvent $event): void
    {
        $event->acl->allow('administrator', $this->routeNamePrefix . 'dashboard.read', PrivilegeInterface::READ);
    }
}
