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

namespace Ims\Manifest;

use Ims\Manifest\Middleware\ProcessManifestUploadMiddleware;
use Ims\Manifest\RequestHandler\ManifestDetailHandler;
use Ims\Manifest\RequestHandler\ManifestListHandler;
use Ims\Manifest\RequestHandler\ManifestUploadHandler;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouteProviderInterface;
use Override;

final readonly class RouteProvider implements RouteProviderInterface
{
    public function __construct(
        private string $routeSegment,
        private string $routeNamePrefix,
    ) {}

    #[Override]
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        $routeCollector->get(
            '/' . $this->routeSegment,
            $middlewareFactory->prepare([
                ManifestListHandler::class,
            ]),
            $this->routeNamePrefix . 'list'
        )->setOptions([
            'navigation' => 'main',
            'label'      => 'Manifests',
            'icon'       => 'bi-clipboard2-data',
            'parent'     => null,
            'order'      => 20,
        ]);

        $routeCollector->get(
            '/' . $this->routeSegment . '/upload',
            $middlewareFactory->prepare([
                ManifestUploadHandler::class,
            ]),
            $this->routeNamePrefix . 'upload'
        );

        $routeCollector->post(
            '/' . $this->routeSegment . '/upload',
            $middlewareFactory->prepare([
                ProcessManifestUploadMiddleware::class,
                ManifestUploadHandler::class,
            ]),
            $this->routeNamePrefix . 'upload.store'
        );

        $routeCollector->get(
            '/' . $this->routeSegment . '/{id:\d+}',
            $middlewareFactory->prepare([
                ManifestDetailHandler::class,
            ]),
            $this->routeNamePrefix . 'detail'
        );
    }
}
