<?php

declare(strict_types=1);

namespace Webware\ConfigManagerTestResource;

class FooConfigProvider
{
    public const TARGET_FILE = 'foo.global.php';

    public function __invoke(): array
    {
        return [
            static::class => $this->getWebwareConfig(),
        ];
    }

    public function getWebwareConfig(): array
    {
        return [
            'baz'     => 'bat',
            'key_old' => 'key_old',
        ];
    }
}
