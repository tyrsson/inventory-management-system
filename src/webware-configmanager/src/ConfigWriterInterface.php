<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

interface ConfigWriterInterface
{
    public function writeConfig(string $targetFile): void;
}
