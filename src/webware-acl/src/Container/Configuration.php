<?php

declare(strict_types=1);

namespace Webware\Acl\Container;

use Psr\Container\ContainerInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;
use Webware\Core\Configuration as Config;
use Webware\Core\Exception;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = AclInterface::class;

    public const string ADMIN_ROUTE_SEGMENT_VALUE = 'acl.manager';

    public const string ADMIN_ROUTE_NAME_PREFIX_VALUE = 'acl.manager.';

    public static function getAssertionManagerConfig(
        ContainerInterface $container,
        string $callingFactory,
    ): array {
        $config = $container->get('config');

        if (! isset($config[AssertionManager::class])) {
            throw Exception\ContainerException::forMissingConfigKey(AssertionManager::class, $callingFactory);
        }

        if (! is_array($config[AssertionManager::class]) || $config[AssertionManager::class] === []) {
            throw Exception\ContainerException::forInvalidConfigType(AssertionManager::class, 'array', get_debug_type($config[AssertionManager::class]), $callingFactory);
        }

        return $config[AssertionManager::class];
    }
}
