<?php

declare(strict_types=1);

namespace Webware\ConfigManagerTest;

use Webware\ConfigManager\ConfigWriter;
use Webware\ConfigManagerTestResource\FooConfigProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(ConfigWriter::class)]
final class ConfigWriterTest extends TestCase
{
    public const CONFIG_FILE = __DIR__ . '/../config/test.global.php';
    private string $dir;
    protected string $targetFile = FooConfigProvider::TARGET_FILE;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/axleus_config_writer';
        if (! is_dir($this->dir)) {
            mkdir($this->dir);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dir . '/' . FooConfigProvider::TARGET_FILE)) {
            @unlink($this->dir . '/' . FooConfigProvider::TARGET_FILE);
        }
        @rmdir($this->dir);
    }

    public function testConfigWriterCanWriteConfig(): void
    {
        $writer = new ConfigWriter([
            FooConfigProvider::class,
        ]);
        $writer->writeConfig($this->dir . '/' . FooConfigProvider::TARGET_FILE);
        self::assertFileExists($this->dir . '/' . FooConfigProvider::TARGET_FILE);
    }
}
