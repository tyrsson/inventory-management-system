<?php

declare(strict_types=1);

namespace Ims\Manifest\Listener;

use Ims\Manifest\Repository\ManifestRepositoryInterface;
use Ims\Manifest\Widget\ManifestDashboardWidget;
use Webware\Admin\Event\RegisterWidgetEvent;

/**
 * Contributes the Manifest Management widget to the admin dashboard.
 *
 * Invoked on RegisterWidgetEvent; fetches a live manifest count and
 * registers a ManifestDashboardWidget with that count.
 */
final class RegisterManifestWidgetListener
{
    public function __construct(
        private readonly ManifestRepositoryInterface $manifests,
    ) {}

    public function __invoke(RegisterWidgetEvent $event): void
    {
        $event->registerWidget(new ManifestDashboardWidget(
            manifestCount: $this->manifests->countAll(),
        ));
    }
}
