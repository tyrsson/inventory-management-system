<?php

declare(strict_types=1);

namespace PhpDb\Session;

use Mezzio\Session\SessionPersistenceInterface;
use PhpDb\Session\Container\DbSessionHandlerFactory;
use PhpDb\Session\Container\PhpDbSessionPersistenceFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                // PhpDbSessionPersistence is the default — async-safe, no ext-session.
                // PhpSessionPersistenceDecorator is available for sync-only environments
                // but is NOT aliased here. Swap the alias below to use it instead.
                SessionPersistenceInterface::class => PhpDbSessionPersistence::class,
            ],
            'factories' => [
                DbSessionHandler::class        => DbSessionHandlerFactory::class,
                PhpDbSessionPersistence::class => PhpDbSessionPersistenceFactory::class,
            ],
        ];
    }
}
