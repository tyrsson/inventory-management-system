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

use Ims\Manifest\Container\Configuration;
use Ims\Manifest\View\Helper\ManifestUrl;
use Ims\Manifest\View\Helper\ManifestUrlFactory;
use Ims\Store\Acl\StoreOwnedResourceAssertion;
use Webware\Acl\AclInterface;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;

final readonly class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'             => $this->getDependencies(),
            'listeners'                => $this->getListeners(),
            'router'                   => $this->getRouteProviders(),
            'templates'                => $this->getTemplates(),
            'view_helpers'             => $this->getViewHelpers(),
            CommandBusInterface::class => $this->getBusConfig(),
            AclInterface::class        => $this->getAclConfig(),
            Configuration::CONFIG_KEY  => $this->getDefaultConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                Repository\ManifestRepositoryInterface::class => Repository\ManifestRepository::class,
            ],
            'factories' => [
                Repository\ManifestRepository::class                                              => Repository\ManifestRepositoryFactory::class,
                RequestHandler\ManifestListHandler::class                                         => RequestHandler\Container\ManifestListHandlerFactory::class,
                RequestHandler\ManifestDetailHandler::class                                       => RequestHandler\Container\ManifestDetailHandlerFactory::class,
                RequestHandler\ManifestUploadHandler::class                                       => RequestHandler\Container\ManifestUploadHandlerFactory::class,
                Middleware\ProcessManifestUploadMiddleware::class                                  => Middleware\Container\ProcessManifestUploadMiddlewareFactory::class,
                Csv\ManifestCsvParser::class                                                      => Csv\ManifestCsvParserFactory::class,
                RouteProvider::class                                                               => Container\RouteProviderFactory::class,
                Listener\RegisterManifestWidgetListener::class                                    => Container\RegisterManifestWidgetListenerFactory::class,
                CommandHandler\UploadManifestHandler::class                                       => CommandHandler\Container\UploadManifestHandlerFactory::class,
                ManifestUrl::class                                                                 => ManifestUrlFactory::class,
            ],
        ];
    }

    public function getListeners(): array
    {
        return [
            RegisterWidgetEvent::class => [
                ['listener' => Listener\RegisterManifestWidgetListener::class, 'priority' => 1],
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

    public function getTemplates(): array
    {
        return [
            'paths' => [
                'manifest' => [__DIR__ . '/../templates/manifest'],
            ],
        ];
    }

    public function getBusConfig(): array
    {
        return [
            BusProvider::COMMAND_MAP_KEY => [
                Command\UploadManifestCommand::class => CommandHandler\UploadManifestHandler::class,
            ],
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'resources' => [
                Configuration::ROUTE_NAME_PREFIX_VALUE . 'list',
                Configuration::ROUTE_NAME_PREFIX_VALUE . 'upload',
                Configuration::ROUTE_NAME_PREFIX_VALUE . 'upload.store',
                Configuration::ROUTE_NAME_PREFIX_VALUE . 'detail',
                // TODO: 'admin.manifest' is a legacy non-route resource ID used by ManifestDashboardWidget.
                // Replace with a proper admin route name (e.g. ims.manifest.admin.manager) once the
                // manifest module is refactored to follow the manager route pattern.
                'admin.manifest',
            ],
            'allow'     => [
                'Member' => [
                    Configuration::ROUTE_NAME_PREFIX_VALUE . 'list',
                    Configuration::ROUTE_NAME_PREFIX_VALUE . 'detail',
                ],
                'Warehouse' => [
                    Configuration::ROUTE_NAME_PREFIX_VALUE . 'upload'       => [StoreOwnedResourceAssertion::class],
                    Configuration::ROUTE_NAME_PREFIX_VALUE . 'upload.store' => [StoreOwnedResourceAssertion::class],
                ],
                // TODO: placeholder rule — move to a proper admin route allow entry during manifest refactor.
                'Warehouse Supervisor' => [
                    'admin.manifest',
                ],
            ],
        ];
    }

    public function getViewHelpers(): array
    {
        return [
            'aliases'   => [
                'manifestUrl' => ManifestUrl::class,
            ],
            'factories' => [
                ManifestUrl::class => ManifestUrlFactory::class,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            Configuration::ROUTE_SEGMENT_KEY     => Configuration::ROUTE_SEGMENT_VALUE,
            Configuration::ROUTE_NAME_PREFIX_KEY => Configuration::ROUTE_NAME_PREFIX_VALUE,
        ];
    }
}
