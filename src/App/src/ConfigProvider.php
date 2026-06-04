<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use App\CommandBus\Middleware\CommandLoggingMiddleware;
use App\CommandBus\Middleware\Container\CommandLoggingMiddlewareFactory;
use App\Container\Configuration;
use Webware\Acl\AclInterface;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;

/**
 * @phpstan-type dependencyArray array{
 *                      delegators?: array<class-string, list<class-string>>,
 *                      factories?: array<class-string, class-string>,
 *                      invokables?: array<class-string, class-string>
 *               }
 * @phpstan-type routeProviderArray array{
 *                      route-providers: list<class-string>
 *                }
 * @phpstan-type templateArray array{
 *                      map: array<string, string>,
 *                      paths: array<string, list<string>>,
 *                      default_layout: string
 *                }
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @phpstan-return array{dependencies: dependencyArray, templates: templateArray}
     */
    public function __invoke(): array
    {
        return [
            'dependencies'             => $this->getDependencies(),
            'router'                   => $this->getRouteProviders(),
            'templates'                => $this->getTemplates(),
            'view_helpers'             => $this->getViewHelpers(),
            CommandBusInterface::class => $this->getBusConfig(),
            AclInterface::class        => $this->getAclConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @phpstan-return dependencyArray
     */
    public function getDependencies(): array
    {
        return [
            'factories'  => [
                CommandLoggingMiddleware::class          => CommandLoggingMiddlewareFactory::class,
                Middleware\ImsMessengerMiddleware::class => Middleware\ImsMessengerMiddlewareFactory::class,
                RequestHandler\DashboardHandler::class   => RequestHandler\Container\DashboardHandlerFactory::class,
                RouteProvider::class                     => Container\RouteProviderFactory::class,
            ],
            'invokables' => [
                RequestHandler\PingHandler::class => RequestHandler\PingHandler::class,
            ],
        ];
    }

    /**
     * Returns the route provider configuration
     *
     * @phpstan-return routeProviderArray
     */
    public function getRouteProviders(): array
    {
        return [
            'route-providers' => [
                RouteProvider::class,
            ],
        ];
    }

    public function getViewHelpers(): array
    {
        return [
            'aliases'   => [
                'imsMessenger' => View\Helper\ImsMessenger::class,
            ],
            'factories' => [
                View\Helper\ImsMessenger::class => View\Helper\ImsMessengerFactory::class,
            ],
        ];
    }

    public function getBusConfig(): array
    {
        return [
            BusProvider::MIDDLEWARE_PIPELINE_KEY => [
                [
                    'middleware' => CommandLoggingMiddleware::class,
                    'priority'   => 0,
                ],
            ],
        ];
    }

    /**
     * Returns the templates configuration
     *
     * @phpstan-return templateArray
     */
    public function getTemplates(): array
    {
        return [
            'map'            => [
                'layout::default' => __DIR__ . '/../templates/layout/default.phtml',
                'app::home-page'  => __DIR__ . '/../templates/app/home-page.phtml',
                'error::404'      => __DIR__ . '/../templates/error/404.phtml',
                'error::error'    => __DIR__ . '/../templates/error/error.phtml',
            ],
            'paths'          => [
                'app'   => [__DIR__ . '/../templates/app'],
                'error' => [__DIR__ . '/../templates/error'],
            ],
            'default_layout' => 'layout::default',
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'resources' => [
                Configuration::ROUTE_NAME_PREFIX_VALUE . 'dashboard' => true,
            ],
            'allow'     => [
                'Member' => [
                    Configuration::ROUTE_NAME_PREFIX_VALUE . 'dashboard' => [],
                ],
            ],
        ];
    }
}
