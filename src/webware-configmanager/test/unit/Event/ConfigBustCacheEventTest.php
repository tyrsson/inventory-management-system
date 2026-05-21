<?php

declare(strict_types=1);

namespace Webware\ConfigManagerTest\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;

#[CoversClass(ConfigBustCacheEvent::class)]
final class ConfigBustCacheEventTest extends TestCase
{
    public function testDefaultCache(): void
    {
        $event = new ConfigBustCacheEvent();
        self::assertSame(ConfigBustCacheEvent::DEFAULT_CACHE, $event->targetCache);
    }

    public function testCustomCache(): void
    {
        $event = new ConfigBustCacheEvent(targetCache: '/tmp/cache.php');
        self::assertSame('/tmp/cache.php', $event->targetCache);
    }

    public function testStopPropagation(): void
    {
        $event = new ConfigBustCacheEvent();
        self::assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }
}
