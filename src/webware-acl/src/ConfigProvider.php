<?php

declare(strict_types=1);

namespace Webware\Acl;

use Webware\Acl\Acl;
use Webware\Acl\AclBuilder;
use Webware\Acl\AclInterface;
use Webware\Acl\Container\AclBuilderFactory;
use Webware\Acl\Container\AclFactory;
use Webware\Acl\Container\CommandHandlerMiddlewareFactory;
use Webware\Acl\Container\IdentityMiddlewareFactory;
use Webware\Acl\Container\RouteProviderFactory;
use Webware\Acl\Middleware\AuthorizationMiddleware;
use Webware\Acl\Middleware\Container\AuthorizationMiddlewareFactory;
use Webware\Acl\Middleware\IdentityMiddleware;
use Webware\Acl\RequestHandler\Container\ForbiddenHandlerFactory;
use Webware\Acl\RequestHandler\ForbiddenHandler;
use Webware\Acl\RequestHandler\ForbiddenHandlerInterface;
use Webware\Acl\Admin\Command\DeleteRoleCommand;
use Webware\Acl\Admin\Command\SaveRoleCommand;
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\Admin\CommandHandler\Container\DeleteRoleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\SaveRoleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\SaveRuleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\UpdateRuleTypeHandlerFactory;
use Webware\Acl\Admin\CommandHandler\DeleteRoleHandler;
use Webware\Acl\Admin\CommandHandler\SaveRoleHandler;
use Webware\Acl\Admin\CommandHandler\SaveRuleHandler;
use Webware\Acl\Admin\CommandHandler\UpdateRuleTypeHandler;
use Webware\Acl\Admin\Dashboard\Container\RegisterWidgetListenerFactory;
use Webware\Acl\Admin\Dashboard\RegisterWidgetListener;
use Webware\Acl\Admin\Middleware\BuildAccessControlMiddleware;
use Webware\Acl\Admin\Middleware\Container\BuildAccessControlMiddlewareFactory;
use Webware\Acl\Admin\Middleware\Container\ProcessRoleMiddlewareFactory;
use Webware\Acl\Admin\Middleware\Container\ProcessRuleMiddlewareFactory;
use Webware\Acl\Admin\Middleware\ProcessRoleMiddleware;
use Webware\Acl\Admin\Middleware\ProcessRuleMiddleware;
use Webware\Acl\Admin\RequestHandler\AclOverviewHandler;
use Webware\Acl\Admin\RequestHandler\Container\AclOverviewHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\ResourceListHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\RoleListHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\RuleManagerHandlerFactory;
use Webware\Acl\Admin\RequestHandler\ResourceListHandler;
use Webware\Acl\Admin\RequestHandler\RoleListHandler;
use Webware\Acl\Admin\RequestHandler\RuleManagerHandler;
use Webware\Admin\Container\Configuration as AdminConfiguration;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;
use Webware\CommandBus\Middleware\CommandHandlerMiddleware;

final class ConfigProvider
{
    /**
     * Returns the configuration array.
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies'           => $this->getDependencies(),
            'listeners'              => $this->getListeners(),
            'router'                 => $this->getRouteProviders(),
            'templates'              => $this->getTemplates(),
            AclInterface::class      => $this->getDefaultConfig(),
            CommandBusInterface::class => $this->getBusConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                AclInterface::class              => Acl::class,
                ForbiddenHandlerInterface::class => ForbiddenHandler::class,
            ],
            'invokables' => [],
            'factories' => [
                Acl::class                           => AclFactory::class,
                AclBuilder::class                    => AclBuilderFactory::class,
                BuildAccessControlMiddleware::class  => BuildAccessControlMiddlewareFactory::class,
                ForbiddenHandler::class              => ForbiddenHandlerFactory::class,
                AclOverviewHandler::class            => AclOverviewHandlerFactory::class,
                AuthorizationMiddleware::class       => AuthorizationMiddlewareFactory::class,
                IdentityMiddleware::class            => IdentityMiddlewareFactory::class,
                RegisterWidgetListener::class        => RegisterWidgetListenerFactory::class,
                ResourceListHandler::class           => ResourceListHandlerFactory::class,
                RoleListHandler::class               => RoleListHandlerFactory::class,
                RouteProvider::class                 => RouteProviderFactory::class,
                RuleManagerHandler::class            => RuleManagerHandlerFactory::class,
                ProcessRuleMiddleware::class         => ProcessRuleMiddlewareFactory::class,
                ProcessRoleMiddleware::class         => ProcessRoleMiddlewareFactory::class,
                DeleteRoleHandler::class             => DeleteRoleHandlerFactory::class,
                SaveRoleHandler::class               => SaveRoleHandlerFactory::class,
                SaveRuleHandler::class               => SaveRuleHandlerFactory::class,
                UpdateRuleTypeHandler::class         => UpdateRuleTypeHandlerFactory::class,
                CommandHandlerMiddleware::class      => CommandHandlerMiddlewareFactory::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        return [
            'paths' => [
                'acl' => [__DIR__ . '/../templates/acl'],
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

    public function getListeners(): array
    {
        return [
            RegisterWidgetEvent::class => [
                ['listener' => RegisterWidgetListener::class, 'priority' => 1],
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'route_param_map'     => [],
            'forbidden_redirect'  => '/',
            'forbidden_template'  => null,
            Container\Configuration::ADMIN_ROUTE_SEGMENT_KEY     => Container\Configuration::ADMIN_ROUTE_SEGMENT_VALUE,
            Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_KEY => Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE,
            'roles'     => [
                'Developer' => ['Administrator'],
            ],
            'resources' => [
                AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . rtrim(Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE, '.'),
            ],
            'allow'     => [
                'Developer' => [
                    AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . rtrim(Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE, '.'),
                ],
            ],
        ];
    }

    public function getBusConfig(): array
    {
        return [
            BusProvider::COMMAND_MAP_KEY => [
                SaveRoleCommand::class        => SaveRoleHandler::class,
                DeleteRoleCommand::class      => DeleteRoleHandler::class,
                SaveRuleCommand::class        => SaveRuleHandler::class,
                UpdateRuleTypeCommand::class  => UpdateRuleTypeHandler::class,
            ],
        ];
    }
}
