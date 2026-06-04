<?php

declare(strict_types=1);

namespace Webware\Acl\Admin\Dashboard;

use Override;
use Webware\Admin\Widget\WidgetInterface;

/**
 * Admin dashboard widget contributed by webware-acl.
 *
 * Links to the ACL Management pages (roles, resources, rules, routes).
 * Visible only to roles with 'read' access to 'admin.acl' (Developer only).
 */
final class Widget implements WidgetInterface
{
    public string $title      { get => 'ACL Management'; }

    public string $privilege  { get => 'read'; }

    public string $template   { get => 'acl::admin-widget'; }

    public int $order      { get => 10; }

    public function __construct(
        public readonly string $resourceId,
        public readonly int $roleCount,
        public readonly int $resourceCount,
        public readonly int $ruleCount,
        public readonly int $assertionCount,
        public readonly int $aclVersion,
    ) {}

    #[Override]
    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
