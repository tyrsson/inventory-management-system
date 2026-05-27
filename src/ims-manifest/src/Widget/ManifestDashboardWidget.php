<?php

declare(strict_types=1);

namespace Ims\Manifest\Widget;

use Override;
use Webware\Admin\Widget\WidgetInterface;

/**
 * Admin dashboard widget contributed by ims-manifest.
 *
 * Links to manifest administration pages.
 * Visible to roles with 'read' access to 'manifest' (Warehouse and above).
 */
final class ManifestDashboardWidget implements WidgetInterface
{
    public string $title      { get => 'Manifest Management'; }

    public string $resourceId { get => 'admin.manifest'; }

    public string $privilege  { get => 'read'; }

    public string $template   { get => 'manifest::admin-widget'; }

    public int    $order      { get => 20; }

    public function __construct(
        public readonly int $manifestCount,
    ) {}

    #[Override]
    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
