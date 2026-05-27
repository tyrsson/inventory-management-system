<?php

declare(strict_types=1);

namespace Webware\ConfigManagerTest;

use Laminas\ConfigAggregator\ArrayProvider;
use Webware\ConfigManager\ConfigWriter;
use Webware\ConfigManagerTestResource\FooConfigProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
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

    // ── deduplicateLists tests ────────────────────────────────────────────────

    public function testWriteDeduplicatesFlatListWhenTwoProvidersContributeSameValues(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter([
            new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.one', 'route.two']]]]),
            new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.one', 'route.two']]]]),
        ]);
        $writer->writeConfig($file);

        $written = require $file;
        self::assertSame(
            ['route.one', 'route.two'],
            $written['acl']['allow']['RoleA'],
            'Duplicate entries from two providers must be deduplicated to a unique list.'
        );
    }

    public function testWriteDeduplicatesAssertionListsUnderAllowRules(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter([
            new ArrayProvider(['acl' => ['allow' => ['Warehouse' => ['ims.upload' => ['Store Owned Resource']]]]]),
            new ArrayProvider(['acl' => ['allow' => ['Warehouse' => ['ims.upload' => ['Store Owned Resource']]]]]),
        ]);
        $writer->writeConfig($file);

        $written = require $file;
        self::assertSame(
            ['Store Owned Resource'],
            $written['acl']['allow']['Warehouse']['ims.upload'],
            'Duplicate assertion aliases contributed by two providers must be collapsed to one.'
        );
    }

    public function testWritePreservesDistinctValuesInList(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter([
            new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.one']]]]),
            new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.two']]]]),
        ]);
        $writer->writeConfig($file);

        $written = require $file;
        self::assertCount(
            2,
            $written['acl']['allow']['RoleA'],
            'Distinct values from two providers must both be present after deduplication.'
        );
        self::assertContains('route.one', $written['acl']['allow']['RoleA']);
        self::assertContains('route.two', $written['acl']['allow']['RoleA']);
    }

    public function testWriteDeduplicatesListsAtMultipleNestingLevels(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter([
            new ArrayProvider(['listeners' => ['EventA' => ['ListenerOne', 'ListenerTwo']]]),
            new ArrayProvider(['listeners' => ['EventA' => ['ListenerOne', 'ListenerTwo']]]),
        ]);
        $writer->writeConfig($file);

        $written = require $file;
        self::assertSame(
            ['ListenerOne', 'ListenerTwo'],
            $written['listeners']['EventA'],
            'Nested list deduplication must work at any depth.'
        );
    }

    public function testWriteDoesNotDeduplicateAssociativeMapKeys(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter([
            new ArrayProvider(['routes' => ['home' => '/home', 'login' => '/login']]),
        ]);
        $writer->writeConfig($file);

        $written = require $file;
        self::assertArrayHasKey('home', $written['routes']);
        self::assertArrayHasKey('login', $written['routes']);
    }

    public function testWritePreservesDuplicatesWhenDeduplicateListsIsFalse(): void
    {
        $file   = $this->dir . '/' . FooConfigProvider::TARGET_FILE;
        $writer = new ConfigWriter(
            providers: [
                new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.one', 'route.two']]]]),
                new ArrayProvider(['acl' => ['allow' => ['RoleA' => ['route.one', 'route.two']]]]),
            ],
            deduplicateLists: false,
        );
        $writer->writeConfig($file);

        $written = require $file;
        self::assertSame(
            ['route.one', 'route.two', 'route.one', 'route.two'],
            $written['acl']['allow']['RoleA'],
            'When deduplicateLists is false the raw merged list must be written as-is.'
        );
    }
}
