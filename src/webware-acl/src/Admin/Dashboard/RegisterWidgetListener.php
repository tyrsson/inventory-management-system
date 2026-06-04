<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Dashboard;

use Webware\Admin\Event\RegisterWidgetEvent;

/**
 * Contributes the ACL Management widget to the admin dashboard.
 *
 * Invoked on RegisterWidgetEvent; fetches live stat counts from the
 * repository and registers an AclDashboardWidget with those counts.
 */
final class RegisterWidgetListener
{
    public function __construct(
        private readonly string $resourceId,
        private readonly array $config,
    ) {}

    public function __invoke(RegisterWidgetEvent $event): void
    {
        // @todo Derive counts from $this->config once config shape is finalised
        $event->registerWidget(new Widget(
            resourceId: $this->resourceId,
            roleCount: 0,
            resourceCount: 0,
            ruleCount: 0,
            assertionCount: 0,
            aclVersion: 0,
        ));
    }
}
