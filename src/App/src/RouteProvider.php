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

use App\Container\Configuration;
use App\RequestHandler\DashboardHandler;
use App\RequestHandler\PingHandler;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouteProviderInterface;

final class RouteProvider implements RouteProviderInterface
{
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        $routeCollector->get(
            '/',
            $middlewareFactory->prepare(
                [
                    DashboardHandler::class,
                ]
            ),
            Configuration::ROUTE_NAME_PREFIX_VALUE . 'dashboard'
        )->setOptions([
            'navigation' => 'main',
            'label'      => 'Dashboard',
            'icon'       => 'bi-speedometer2',
            'parent'     => null,
            'order'      => 10,
        ]);

        $routeCollector->get(
            '/ping',
            $middlewareFactory->prepare(
                [
                    PingHandler::class,
                ]
            ),
            Configuration::ROUTE_NAME_PREFIX_VALUE . 'api.ping'
        );
    }
}
