<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\InputFilter\InputFilterFactory;
use Webware\Acl\Admin\Command\DeleteRoleCommand;
use Webware\Acl\Admin\Command\DeleteRuleCommand;
use Webware\Acl\Admin\Command\SaveRoleCommand;
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\Admin\CommandHandler\Container\DeleteRoleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\DeleteRuleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\SaveRoleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\SaveRuleHandlerFactory;
use Webware\Acl\Admin\CommandHandler\Container\UpdateRuleTypeHandlerFactory;
use Webware\Acl\Admin\CommandHandler\DeleteRoleHandler;
use Webware\Acl\Admin\CommandHandler\DeleteRuleHandler;
use Webware\Acl\Admin\CommandHandler\SaveRoleHandler;
use Webware\Acl\Admin\CommandHandler\SaveRuleHandler;
use Webware\Acl\Admin\CommandHandler\UpdateRuleTypeHandler;
use Webware\Acl\Admin\Dashboard\Container\RegisterWidgetListenerFactory;
use Webware\Acl\Admin\Dashboard\RegisterWidgetListener;
use Webware\Acl\Admin\Middleware\OverviewMiddleware;
use Webware\Acl\Admin\Middleware\Container\OverviewMiddlewareFactory;
use Webware\Acl\Admin\Middleware\Container\ProcessRoleMiddlewareFactory;
use Webware\Acl\Admin\Middleware\Container\ProcessRuleMiddlewareFactory;
use Webware\Acl\Admin\Middleware\ProcessRoleMiddleware;
use Webware\Acl\Admin\Middleware\ProcessRuleMiddleware;
use Webware\Acl\Admin\RequestHandler\AclOverviewHandler;
use Webware\Acl\Admin\RequestHandler\Container\AclOverviewHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\AddRoleModalHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\DeleteRuleModalHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\EditRoleModalHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\ResourceListHandlerFactory;
use Webware\Acl\Admin\RequestHandler\Container\RoleListHandlerFactory;
use Webware\Acl\Admin\RequestHandler\AddRoleModalHandler;
use Webware\Acl\Admin\RequestHandler\DeleteRuleModalHandler;
use Webware\Acl\Admin\RequestHandler\EditRoleModalHandler;
use Webware\Acl\Admin\RequestHandler\ResourceListHandler;
use Webware\Acl\Admin\RequestHandler\RoleListHandler;
use Webware\Acl\Container\AclFactory;
use Webware\Acl\Container\RouteProviderFactory;
use Webware\Acl\Http\Container\RouteResourceFactoryFactory;
use Webware\Acl\Http\RouteResourceFactory;
use Webware\Acl\Http\RouteResourceFactoryInterface;
use Webware\Acl\Middleware\AclMiddleware;
use Webware\Acl\Middleware\AuthorizationMiddleware;
use Webware\Acl\Middleware\Container\AclMiddlewareFactory;
use Webware\Acl\Middleware\Container\AuthorizationMiddlewareFactory;
use Webware\Acl\Repository\Container\RoleRepositoryFactory;
use Webware\Acl\Repository\Container\RuleRepositoryFactory;
use Webware\Acl\Repository\RoleRepository;
use Webware\Acl\Repository\RuleRepository;
use Webware\Acl\RequestHandler\Container\ForbiddenHandlerFactory;
use Webware\Acl\RequestHandler\ForbiddenHandler;
use Webware\Acl\RequestHandler\ForbiddenHandlerInterface;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;

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
            'dependencies'             => $this->getDependencies(),
            'input_filters'            => $this->getInputFilterConfig(),
            'listeners'                => $this->getListeners(),
            'router'                   => $this->getRouteProviders(),
            'templates'                => $this->getTemplates(),
            AclInterface::class        => $this->getDefaultConfig(),
            AssertionManager::class    => $this->getAssertionManagerConfig(),
            CommandBusInterface::class => $this->getBusConfig(),
            'validators'               => $this->getValidatorConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                AclInterface::class                  => Acl::class,
                ForbiddenHandlerInterface::class     => ForbiddenHandler::class,
                RouteResourceFactoryInterface::class => RouteResourceFactory::class,
            ],
            'factories' => [
                Acl::class                                 => AclFactory::class,
                Assertion\AssertionAggregateFactory::class => Assertion\AssertionAggregateFactoryFactory::class,
                AssertionManager::class                    => Container\AssertionManagerFactory::class,
                RouteResourceFactory::class                => RouteResourceFactoryFactory::class,
                OverviewMiddleware::class                  => OverviewMiddlewareFactory::class,
                ForbiddenHandler::class                    => ForbiddenHandlerFactory::class,
                AclOverviewHandler::class                  => AclOverviewHandlerFactory::class,
                DeleteRuleModalHandler::class              => DeleteRuleModalHandlerFactory::class,
                AddRoleModalHandler::class                 => AddRoleModalHandlerFactory::class,
                EditRoleModalHandler::class                => EditRoleModalHandlerFactory::class,
                AclMiddleware::class                       => AclMiddlewareFactory::class,
                AuthorizationMiddleware::class             => AuthorizationMiddlewareFactory::class,
                RegisterWidgetListener::class              => RegisterWidgetListenerFactory::class,
                ResourceListHandler::class                 => ResourceListHandlerFactory::class,
                RoleListHandler::class                     => RoleListHandlerFactory::class,
                RouteProvider::class                       => RouteProviderFactory::class,
                ProcessRuleMiddleware::class               => ProcessRuleMiddlewareFactory::class,
                ProcessRoleMiddleware::class               => ProcessRoleMiddlewareFactory::class,
                DeleteRoleHandler::class                   => DeleteRoleHandlerFactory::class,
                DeleteRuleHandler::class                   => DeleteRuleHandlerFactory::class,
                SaveRoleHandler::class                     => SaveRoleHandlerFactory::class,
                SaveRuleHandler::class                     => SaveRuleHandlerFactory::class,
                UpdateRuleTypeHandler::class               => UpdateRuleTypeHandlerFactory::class,
                RoleRepository::class                      => RoleRepositoryFactory::class,
                RuleRepository::class                      => RuleRepositoryFactory::class,
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

    public function getInputFilterConfig(): array
    {
        return [
            'factories' => [
                InputFilter\RuleDataFilter::class => InputFilter\Container\RuleDataFilterFactory::class,
                InputFilter\RoleDataFilter::class => InputFilterFactory::class,
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
            'route_param_map'                                    => [],
            'forbidden_redirect'                                 => '/',
            'forbidden_template'                                 => null,
            Container\Configuration::ADMIN_ROUTE_SEGMENT_KEY     => Container\Configuration::ADMIN_ROUTE_SEGMENT_VALUE,
            Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_KEY => Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE,
        ];
    }

    public function getAssertionManagerConfig(): array
    {
        return [
            'aliases'   => [
                'Ownership' => Assertion\OwnershipAssertion::class,
            ],
            'factories' => [
                // OwnershipAssertion defines __invoke(): static, making it usable as its own factory key.
                Assertion\OwnershipAssertion::class => Assertion\OwnershipAssertion::class,
            ],
        ];
    }

    public function getBusConfig(): array
    {
        return [
            BusProvider::COMMAND_MAP_KEY => [
                SaveRoleCommand::class       => SaveRoleHandler::class,
                DeleteRoleCommand::class     => DeleteRoleHandler::class,
                DeleteRuleCommand::class     => DeleteRuleHandler::class,
                SaveRuleCommand::class       => SaveRuleHandler::class,
                UpdateRuleTypeCommand::class => UpdateRuleTypeHandler::class,
            ],
        ];
    }

    public function getValidatorConfig(): array
    {
        return [
            'factories' => [
                Validator\Assertion::class => Validator\Container\AssertionFactory::class,
            ],
        ];
    }
}
