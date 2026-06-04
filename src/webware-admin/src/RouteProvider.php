<?php

declare(strict_types=1);

namespace Webware\Admin;

use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouteProviderInterface;
use Override;
use Webware\Admin\Middleware\DashboardMiddleware;

final readonly class RouteProvider implements RouteProviderInterface
{
    public function __construct(
        private string $adminBasePath,
        private string $routeNamePrefix,
    ) {}

    #[Override]
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        $routeCollector->get(
            '/' . $this->adminBasePath,
            $middlewareFactory->prepare(
                [
                    DashboardMiddleware::class,
                    RequestHandler\DashboardHandler::class,
                ]
            ),
            $this->routeNamePrefix . 'dashboard.read'
        )->setOptions([
            'navigation' => 'admin',
            'label'      => 'Dashboard',
            'icon'       => 'bi-grid-fill',
            'parent'     => null,
            'order'      => 10,
        ]);
    }
}
