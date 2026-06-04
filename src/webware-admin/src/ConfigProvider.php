<?php

declare(strict_types=1);

namespace Webware\Admin;

use Webware\Acl\AclInterface;
use Webware\Admin\Container\DashboardHandlerFactory;
use Webware\Admin\Container\DashboardMiddlewareFactory;
use Webware\Admin\Container\RouteProviderFactory;
use Webware\Admin\Middleware\DashboardMiddleware;
use Webware\Admin\RequestHandler\DashboardHandler;
use Webware\Admin\View\Helper\AdminUrl;
use Webware\Admin\View\Helper\AdminUrlFactory;

final readonly class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'        => $this->getDependencies(),
            'router'              => $this->getRouteProviders(),
            'templates'           => $this->getTemplates(),
            'view_helpers'        => $this->getViewHelpers(),
            AclInterface::class   => $this->getAclConfig(),
            AdminInterface::class => $this->getDefaultConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                DashboardHandler::class    => DashboardHandlerFactory::class,
                DashboardMiddleware::class => DashboardMiddlewareFactory::class,
                RouteProvider::class       => RouteProviderFactory::class,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            Container\Configuration::ADMIN_ROUTE_SEGMENT_KEY     => Container\Configuration::ADMIN_ROUTE_SEGMENT_VALUE,
            Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_KEY => Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE,
        ];
    }

    public function getTemplates(): array
    {
        return [
            'paths' => [
                'admin' => [__DIR__ . '/../templates/admin'],
            ],
        ];
    }

    public function getViewHelpers(): array
    {
        return [
            'aliases'   => [
                'adminUrl' => AdminUrl::class,
            ],
            'factories' => [
                AdminUrl::class => AdminUrlFactory::class,
            ],
        ];
    }

    public function getRouteProviders(): array
    {
        return [
            'route-providers' => [
                RouteProvider::class,
            ],
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'roles'     => [
                'Administrator' => ['Member'],
            ],
            'resources' => [
                Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'dashboard.read' => true,
            ],
            'allow'     => [
                'Administrator' => [
                    Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'dashboard.read' => [],
                ],
            ],
        ];
    }
}
