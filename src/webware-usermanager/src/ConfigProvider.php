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

namespace Webware\UserManager;

use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepositoryInterface;
use Webware\Acl\AclInterface;
use Webware\Acl\Event\ResourcesLoadedEvent;
use Webware\Acl\Event\RulesLoadedEvent;
use Webware\CommandBus\CommandBusInterface;
use Webware\UserManager\Listener\RegisterUserManagerResourcesListener;
use Webware\UserManager\Listener\RegisterUserManagerRulesListener;
use Webware\UserManager\Repository\UserRepositoryInterface as UserRepositoryContract;
use Webware\UserManager\UserInterface;
use Webware\UserManager\View\Helper\UserAdminUrl;
use Webware\UserManager\View\Helper\UserAdminUrlFactory;
use Webware\UserManager\View\Helper\UserUrl;
use Webware\UserManager\View\Helper\UserUrlFactory;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies'             => $this->getDependencies(),
            'listeners'                => $this->getListeners(),
            'router'                   => $this->getRouteProviders(),
            'templates'                => $this->getTemplates(),
            'view_helpers'             => $this->getViewHelpers(),
            'authentication'           => $this->getAuthenticationConfig(),
            CommandBusInterface::class => [
                'command_map' => $this->getCommandMap(),
            ],
            UserInterface::class       => $this->getDefaultConfig(),
            AclInterface::class        => $this->getAclConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                // Bind mezzio-authentication interfaces to our implementations
                UserRepositoryInterface::class => UserRepositoryContract::class,
                UserRepositoryContract::class  => Repository\UserRepository::class,
                AuthenticationInterface::class => PhpSession::class,
            ],
            'factories' => [
                // Registers the user factory under our own interface key.
                // Host app aliases Mezzio\Authentication\UserInterface::class → UserInterface::class.
                UserInterface::class                                => Container\UserFactory::class,
                Admin\RequestHandler\CreateUserHandler::class       => Admin\RequestHandler\Container\CreateUserHandlerFactory::class,
                Admin\RequestHandler\UpdateUserHandler::class       => Admin\RequestHandler\Container\UpdateUserHandlerFactory::class,
                Admin\RequestHandler\ToggleUserActiveHandler::class => Admin\RequestHandler\Container\ToggleUserActiveHandlerFactory::class,
                CommandHandler\SaveUserHandler::class               => CommandHandler\Container\SaveUserHandlerFactory::class,
                Middleware\RegistrationMiddleware::class            => Middleware\Container\RegistrationMiddlewareFactory::class,
                Repository\UserRepository::class                    => Repository\UserRepositoryFactory::class,
                RouteProvider::class                                => Container\RouteProviderFactory::class,
                RequestHandler\LoginHandler::class                  => RequestHandler\Container\LoginHandlerFactory::class,
                RequestHandler\LogoutHandler::class                 => RequestHandler\Container\LogoutHandlerFactory::class,
                RequestHandler\RegistrationHandler::class           => RequestHandler\Container\RegistrationHandlerFactory::class,
                RequestHandler\ResendVerificationHandler::class     => RequestHandler\Container\ResendVerificationHandlerFactory::class,
                RequestHandler\UserListHandler::class               => RequestHandler\Container\UserListHandlerFactory::class,
                RequestHandler\VerifyEmailHandler::class            => RequestHandler\Container\VerifyEmailHandlerFactory::class,
                Listener\SendVerificationEmailListener::class       => Listener\Container\SendVerificationEmailListenerFactory::class,
                Listener\RegisterUserManagerResourcesListener::class => Listener\Container\RegisterUserManagerResourcesListenerFactory::class,
                Listener\RegisterUserManagerRulesListener::class     => Listener\Container\RegisterUserManagerRulesListenerFactory::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function getCommandMap(): array
    {
        return [
            Command\SaveUserCommand::class => CommandHandler\SaveUserHandler::class,
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
                'user' => [__DIR__ . '/../templates/user'],
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            Container\Configuration::ROUTE_SEGMENT_KEY         => Container\Configuration::ROUTE_SEGMENT_VALUE,
            Container\Configuration::ROUTE_NAME_PREFIX_KEY     => Container\Configuration::ROUTE_NAME_PREFIX_VALUE,
            Container\Configuration::ADMIN_ROUTE_SEGMENT_KEY   => Container\Configuration::ADMIN_ROUTE_SEGMENT_VALUE,
            Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_KEY => Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE,
        ];
    }

    public function getViewHelpers(): array
    {
        return [
            'aliases'   => [
                'userUrl'      => UserUrl::class,
                'userAdminUrl' => UserAdminUrl::class,
            ],
            'factories' => [
                UserUrl::class      => UserUrlFactory::class,
                UserAdminUrl::class => UserAdminUrlFactory::class,
            ],
        ];
    }

    public function getListeners(): array
    {
        return [
            ResourcesLoadedEvent::class => [
                ['listener' => RegisterUserManagerResourcesListener::class, 'priority' => 1],
            ],
            RulesLoadedEvent::class     => [
                ['listener' => RegisterUserManagerRulesListener::class, 'priority' => 1],
            ],
        ];
    }

    public function getAuthenticationConfig(): array
    {
        return [
            'redirect' => '/' . Container\Configuration::ROUTE_SEGMENT_VALUE . '/login',
            'username' => 'email',
            'password' => 'password',
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'login_path' => '/' . Container\Configuration::ROUTE_SEGMENT_VALUE . '/login',
            'roles'      => [
                'Guest'  => [],
                'Member' => ['Guest'],
            ],
            'resources'  => [
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.read',
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.create',
            ],
            'allow'      => [
                'Guest' => [
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.read',
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.create',
                ],
            ],
        ];
    }
}
