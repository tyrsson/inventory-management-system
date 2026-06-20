<?php

declare(strict_types=1);

namespace Webware\UserManager\Admin\Dashboard;

use Override;
use Webware\Admin\Widget\WidgetInterface;

final class Widget implements WidgetInterface
{
    public string $title     { get => 'User Management'; }

    public string $privilege  { get => 'read'; }

    public string $template   { get => 'user::admin-widget'; }

    public int    $order      { get => 5; }

    public function __construct(
        public readonly string $resourceId,
        public readonly int $totalUsers,
        public readonly int $activeUsers,
        public readonly int $inactiveUsers,
    ) {}

    #[Override]
    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
