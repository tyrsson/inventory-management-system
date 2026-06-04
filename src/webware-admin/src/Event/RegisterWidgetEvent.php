<?php

declare(strict_types=1);

namespace Webware\Admin\Event;

use ArrayIterator;
use Webware\Admin\Widget\WidgetInterface;

use function usort;

/**
 * Mutable collect event dispatched by DashboardMiddleware.
 *
 * Modules register PSR-14 listeners for this event and call addWidget()
 * to contribute their widget to the admin dashboard.
 */
final class RegisterWidgetEvent
{
    /** @var WidgetInterface[] */
    private array $widgets = [];

    public function registerWidget(WidgetInterface $widget): void
    {
        $this->widgets[] = $widget;
    }

    /**
     * Returns an ArrayIterator of widgets sorted by ascending order value.
     *
     * @return ArrayIterator<int, WidgetInterface>
     */
    public function getIterator(): ArrayIterator
    {
        $widgets = $this->widgets;
        usort($widgets, static fn (WidgetInterface $a, WidgetInterface $b): int => $a->order <=> $b->order);

        return new ArrayIterator($widgets);
    }
}
