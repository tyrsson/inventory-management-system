<?php

declare(strict_types=1);

namespace Webware\AdminTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\Admin\Widget\WidgetInterface;

#[CoversClass(RegisterWidgetEvent::class)]
final class RegisterWidgetEventTest extends TestCase
{
    #[Test]
    public function getIteratorReturnsSortedByOrder(): void
    {
        $event = new RegisterWidgetEvent();
        $event->registerWidget($this->makeWidget(30));
        $event->registerWidget($this->makeWidget(10));
        $event->registerWidget($this->makeWidget(20));

        $widgets = iterator_to_array($event->getIterator(), false);

        self::assertSame([10, 20, 30], array_map(fn ($w) => $w->order, $widgets));
    }

    #[Test]
    public function getIteratorReturnsEmptyWhenNoWidgetsRegistered(): void
    {
        $event = new RegisterWidgetEvent();

        self::assertCount(0, $event->getIterator());
    }

    #[Test]
    public function multipleCallsToGetIteratorReturnIndependentIterators(): void
    {
        $event = new RegisterWidgetEvent();
        $event->registerWidget($this->makeWidget(1));

        $a = $event->getIterator();
        $b = $event->getIterator();

        self::assertNotSame($a, $b);
        self::assertCount(1, $a);
        self::assertCount(1, $b);
    }

    private function makeWidget(int $order, string $resourceId = 'admin.test'): WidgetInterface
    {
        return new class($order, $resourceId) implements WidgetInterface {
            public string $title    { get => 'Test'; }

            public string $template { get => 'test::widget'; }

            public string $privilege { get => 'read'; }

            public function __construct(
                public int $order,
                public string $resourceId,
            ) {}

            public function getResourceId(): string
            {
                return $this->resourceId;
            }
        };
    }
}
