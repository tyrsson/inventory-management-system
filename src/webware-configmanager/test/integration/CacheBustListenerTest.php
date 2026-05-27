<?php

declare(strict_types=1);

namespace Webware\ConfigManagerIntegrationTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Listener\CacheBustListener;

use function file_put_contents;
use function sys_get_temp_dir;
use function unlink;
use function file_exists;

#[CoversClass(CacheBustListener::class)]
final class CacheBustListenerTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/webware_config_cache_test.php';
        file_put_contents($this->cacheFile, '<?php return [];');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    public function testDeletesCacheFile(): void
    {
        $listener = new CacheBustListener(config: ['debug' => false]);
        $event    = new ConfigBustCacheEvent(targetCache: $this->cacheFile);

        $listener($event);

        self::assertFileDoesNotExist($this->cacheFile);
    }

    public function testSkipsInDebugMode(): void
    {
        $listener = new CacheBustListener(config: ['debug' => true]);
        $event    = new ConfigBustCacheEvent(targetCache: $this->cacheFile);

        $listener($event);

        self::assertFileExists($this->cacheFile);
    }
}
