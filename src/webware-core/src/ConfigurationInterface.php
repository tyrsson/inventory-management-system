<?php

declare(strict_types=1);

namespace Webware\Core;

interface ConfigurationInterface
{
    /**
     * Generic top level config key for all
     * Webware packages to use as a container for their config values.
     * Expect this to be overridden by component Interface::class
     * per component
     */
    public const string CONFIG_KEY = 'webware';

    /** Admin config key / values */
    public const string ADMIN_ROUTE_SEGMENT_KEY = 'admin_route_segment';

    public const string ADMIN_ROUTE_SEGMENT_VALUE = 'webware.admin';

    public const string ADMIN_ROUTE_NAME_PREFIX_KEY = 'admin_route_name_prefix';

    public const string ADMIN_ROUTE_NAME_PREFIX_VALUE = 'webware.admin.';

    /** Public API config key / values */
    public const string ROUTE_SEGMENT_KEY = 'route_segment';

    public const string ROUTE_SEGMENT_VALUE = 'webware';

    public const string ROUTE_NAME_PREFIX_KEY = 'route_name_prefix';

    public const string ROUTE_NAME_PREFIX_VALUE = 'webware.';
}
