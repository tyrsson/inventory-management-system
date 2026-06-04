<?php

declare(strict_types=1);

namespace Webware\UserManager\Container;

use Webware\Core\Configuration as Config;
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
}
