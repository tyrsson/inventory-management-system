<?php

declare(strict_types=1);

namespace Webware\UserManager\Container;

use Psr\Container\ContainerInterface;
use Webware\Core\Configuration as Config;
use Webware\Core\Exception;
use Webware\UserManager\UserInterface;

final readonly class Configuration extends Config
{
    public const string CONFIG_KEY = UserInterface::class;

    public const string ROUTE_SEGMENT_VALUE = 'user.manager';

    public const string ROUTE_NAME_PREFIX_VALUE = 'user.manager.';

    public const string ADMIN_ROUTE_SEGMENT_VALUE = 'user.manager';

    public const string ADMIN_ROUTE_NAME_PREFIX_VALUE = 'user.manager.';

    public const string POST_LOGIN_REDIRECT_KEY = 'post_login_redirect';

    public const string POST_LOGIN_REDIRECT_VALUE = '/';

    private const string MEZZIO_AUTH_KEY = 'authentication';

    public static function getCredentialConfig(
        ContainerInterface $container,
        string $callingFactory,
    ): array {
        $config = $container->get('config');

        if (! isset($config[self::MEZZIO_AUTH_KEY])) {
            throw Exception\ContainerException::forMissingConfigKey(self::MEZZIO_AUTH_KEY, $callingFactory);
        }

        if (! is_array($config[self::MEZZIO_AUTH_KEY]) || $config[self::MEZZIO_AUTH_KEY] === []) {
            throw Exception\ContainerException::forInvalidConfigType(
                self::MEZZIO_AUTH_KEY,
                'array',
                get_debug_type(
                    $config[self::MEZZIO_AUTH_KEY],
                ),
                $callingFactory,
            );
        }

        return $config[self::MEZZIO_AUTH_KEY];
    }
}
