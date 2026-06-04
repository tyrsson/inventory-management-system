<?php

declare(strict_types=1);

use App\ConfigProvider;
use Htmx\ConfigProvider as HtmxConfigProvider;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Webware\Acl\ConfigProvider as WebwareAclConfigProvider;
use Webware\Navigation\ConfigProvider as WebwareNavigationConfigProvider;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];

$aggregator = new ConfigAggregator([
    \Laminas\InputFilter\ConfigProvider::class,
    \Laminas\Validator\ConfigProvider::class,
    \Laminas\Filter\ConfigProvider::class,
    \Axleus\Message\ConfigProvider::class,
    \Mezzio\Session\ConfigProvider::class,
    \PhpDb\Session\ConfigProvider::class,
    \Webware\CommandBus\ConfigProvider::class,
    \Axleus\Mailer\ConfigProvider::class,
    \PhpDb\ConfigProvider::class,
    \PhpDb\Mysql\ConfigProvider::class,
    \Axleus\Log\ConfigProvider::class,
    \Laminas\Hydrator\ConfigProvider::class,
    \Phly\EventDispatcher\ConfigProvider::class,
    Laminas\View\ConfigProvider::class,
    Mezzio\LaminasView\ConfigProvider::class,
    Laminas\ServiceManager\ConfigProvider::class,
    Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    Laminas\HttpHandlerRunner\ConfigProvider::class,
    // Include cache configuration
    new ArrayProvider($cacheConfig),
    Mezzio\Helper\ConfigProvider::class,
    Mezzio\ConfigProvider::class,
    Mezzio\Router\ConfigProvider::class,
    Laminas\Diactoros\ConfigProvider::class,
    class_exists(Webware\Traccio\ConfigProvider::class,)
        ? Webware\Traccio\ConfigProvider::class
        : function () {
            return [];
        },
    // Module config
    Webware\Core\ConfigProvider::class,
    Webware\ConfigManager\ConfigProvider::class,
    Webware\Event\ConfigProvider::class,
    Webware\UserManager\ConfigProvider::class,
    WebwareAclConfigProvider::class,
    Webware\Admin\ConfigProvider::class,
    WebwareNavigationConfigProvider::class,
    Ims\Store\ConfigProvider::class,
    Ims\Manifest\ConfigProvider::class,
    // Default App module config
    ConfigProvider::class,
    HtmxConfigProvider::class,
    // Load application config in a pre-defined order in such a way that local settings
    // overwrite global settings. (Loaded as first to last):
    //   - `global.php`
    //   - `*.global.php`
    //   - `local.php`
    //   - `*.local.php`
    new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    // Load development config if it exists
    new PhpFileProvider(realpath(__DIR__) . '/development.config.php'),
], $cacheConfig['config_cache_path']);

return $aggregator->getMergedConfig();
