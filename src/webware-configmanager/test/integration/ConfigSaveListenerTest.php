<?php

declare(strict_types=1);

namespace Webware\ConfigManagerIntegrationTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webimpress\SafeWriter\Exception\ExceptionInterface as FileWriterException;
use Webware\ConfigManager\Event\ConfigBustCacheEvent;
use Webware\ConfigManager\Event\ConfigSaveEvent;
use Webware\ConfigManager\Listener\CacheBustListener;
use Webware\ConfigManager\Listener\ConfigSaveListener;
use Webware\ConfigManagerTestResource\FooConfigProvider;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(ConfigSaveListener::class)]
final class ConfigSaveListenerTest extends TestCase
{
    private string $dir;
    private string $targetFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir        = sys_get_temp_dir() . '/webware_config_save_listener';
        $this->targetFile = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        if (! is_dir($this->dir)) {
            mkdir($this->dir);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->targetFile)) {
            @unlink($this->targetFile);
        }
        @rmdir($this->dir);
    }

    public function testWritesConfigFile(): void
    {
        $config = [
            'debug' => false,
            FooConfigProvider::class => ['baz' => 'bat'],
        ];
        $listener = new ConfigSaveListener(config: $config);

        $event = new ConfigSaveEvent(
            target:        FooConfigProvider::class,
            targetFile:    $this->targetFile,
            updatedConfig: ['key_new' => 'value_new'],
        );

        $listener($event);

        self::assertFileExists($this->targetFile);
        self::assertFalse($event->isPropagationStopped());
    }

    public function testStopsPropagationInDebugMode(): void
    {
        $config = [
            'debug' => true,
            FooConfigProvider::class => [],
        ];
        $listener = new ConfigSaveListener(config: $config);

        $event = new ConfigSaveEvent(
            target:        FooConfigProvider::class,
            targetFile:    $this->targetFile,
            updatedConfig: [],
        );

        $listener($event);

        self::assertTrue($event->isPropagationStopped());
    }
}
