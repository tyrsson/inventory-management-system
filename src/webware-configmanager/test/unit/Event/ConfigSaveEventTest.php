<?php

declare(strict_types=1);

namespace Webware\ConfigManagerTest\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Event\ConfigSaveEvent;

#[CoversClass(ConfigSaveEvent::class)]
final class ConfigSaveEventTest extends TestCase
{
    public function testCanConstructWithRequiredArgs(): void
    {
        $event = new ConfigSaveEvent(
            target:        'SomeProvider',
            targetFile:    'some.local.php',
            updatedConfig: ['key' => 'value'],
        );

        self::assertSame('SomeProvider', $event->target);
        self::assertSame('some.local.php', $event->targetFile);
        self::assertSame(['key' => 'value'], $event->updatedConfig);
        self::assertSame(ConfigBustCacheEvent::DEFAULT_CACHE, $event->targetCache);
    }

    public function testGetTargetFileName(): void
    {
        $event = new ConfigSaveEvent(
            target:        'SomeProvider',
            targetFile:    '/full/path/to/some.local.php',
            updatedConfig: [],
        );

        self::assertSame('some.local.php', $event->getTargetFileName());
    }

    public function testStopPropagation(): void
    {
        $event = new ConfigSaveEvent('P', 'f.php', []);
        self::assertFalse($event->isPropagationStopped());
        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }

    public function testDeduplicateListsDefaultsToTrue(): void
    {
        $event = new ConfigSaveEvent('P', 'f.php', []);
        self::assertTrue($event->deduplicateLists);
    }

    public function testDeduplicateListsCanBeSetToFalse(): void
    {
        $event = new ConfigSaveEvent('P', 'f.php', [], deduplicateLists: false);
        self::assertFalse($event->deduplicateLists);
    }
}
