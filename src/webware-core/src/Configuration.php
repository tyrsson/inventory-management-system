<?php

declare(strict_types=1);

namespace Webware\Core;

use Psr\Container\ContainerInterface;

use function is_string;

readonly class Configuration implements ConfigurationInterface
{
    private function __construct() {}

    final public static function getConfig(ContainerInterface $container, string $callingFactory): array
    {
        if (! $container->has('config')) {
            throw Exception\ContainerException::forMissingConfigService('config', $callingFactory);
        }

        $config = $container->get('config');
        if (! isset($config[static::CONFIG_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(static::CONFIG_KEY, $callingFactory);
        }
        if (
            ! is_array($config[static::CONFIG_KEY]) || $config[static::CONFIG_KEY] === []
        ) {
            throw Exception\ContainerException::forInvalidConfigType(static::CONFIG_KEY, 'non-empty array', get_debug_type($config[static::CONFIG_KEY]), $callingFactory);
        }

        return $config[static::CONFIG_KEY];
    }

    public static function getAdminRouteSegment(ContainerInterface $container, string $callingFactory): string
    {
        $config = static::getConfig($container, $callingFactory);

        if (! isset($config[static::ADMIN_ROUTE_SEGMENT_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(static::ADMIN_ROUTE_SEGMENT_KEY, $callingFactory);
        }

        if (
            ! is_string($config[static::ADMIN_ROUTE_SEGMENT_KEY])
            || $config[static::ADMIN_ROUTE_SEGMENT_KEY] === ''
        ) {
            throw Exception\ContainerException::forInvalidConfigType(static::ADMIN_ROUTE_SEGMENT_KEY, 'non-empty string', get_debug_type($config[static::ADMIN_ROUTE_SEGMENT_KEY]), $callingFactory);
        }

        return $config[static::ADMIN_ROUTE_SEGMENT_KEY];
    }

    public static function getAdminRouteNamePrefix(ContainerInterface $container, string $callingFactory): string
    {
        $config = static::getConfig($container, $callingFactory);

        if (! isset($config[static::ADMIN_ROUTE_NAME_PREFIX_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(static::ADMIN_ROUTE_NAME_PREFIX_KEY, $callingFactory);
        }
        if (
            ! is_string($config[static::ADMIN_ROUTE_NAME_PREFIX_KEY])
            || $config[static::ADMIN_ROUTE_NAME_PREFIX_KEY] === ''
        ) {
            throw Exception\ContainerException::forInvalidConfigType(static::ADMIN_ROUTE_NAME_PREFIX_KEY, 'non-empty string', get_debug_type($config[static::ADMIN_ROUTE_NAME_PREFIX_KEY]), $callingFactory);
        }

        return $config[static::ADMIN_ROUTE_NAME_PREFIX_KEY];
    }

    public static function getRouteSegment(ContainerInterface $container, string $callingFactory): string
    {
        $config = static::getConfig($container, $callingFactory);

        if (! isset($config[static::ROUTE_SEGMENT_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(static::ROUTE_SEGMENT_KEY, $callingFactory);
        }
        if (
            ! is_string($config[static::ROUTE_SEGMENT_KEY])
            || $config[static::ROUTE_SEGMENT_KEY] === ''
        ) {
            throw Exception\ContainerException::forInvalidConfigType(static::ROUTE_SEGMENT_KEY, 'non-empty string', get_debug_type($config[static::ROUTE_SEGMENT_KEY]), $callingFactory);
        }

        return $config[static::ROUTE_SEGMENT_KEY];
    }

    public static function getRouteNamePrefix(ContainerInterface $container, string $callingFactory): string
    {
        $config = static::getConfig($container, $callingFactory);

        if (! isset($config[static::ROUTE_NAME_PREFIX_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(static::ROUTE_NAME_PREFIX_KEY, $callingFactory);
        }
        if (
            ! is_string($config[static::ROUTE_NAME_PREFIX_KEY])
            || $config[static::ROUTE_NAME_PREFIX_KEY] === ''
        ) {
            throw Exception\ContainerException::forInvalidConfigType(static::ROUTE_NAME_PREFIX_KEY, 'non-empty string', get_debug_type($config[static::ROUTE_NAME_PREFIX_KEY]), $callingFactory);
        }

        return $config[static::ROUTE_NAME_PREFIX_KEY];
    }
}
