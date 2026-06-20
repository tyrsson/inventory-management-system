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

use Webware\Acl\AclInterface;
use Webware\Admin\Container\Configuration as AdminConfiguration;
use Webware\CommandBus\CommandBusInterface;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\UserManager\Admin\Dashboard\RegisterWidgetListener;
use Webware\UserManager\Admin\Dashboard\Container\RegisterWidgetListenerFactory;
use Webware\ResultSet\WithRowDataPrototypeInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;
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
            'router'                   => $this->getRouteProviders(),
            'templates'                => $this->getTemplates(),
            'view_helpers'             => $this->getViewHelpers(),
            'authentication'           => $this->getAuthenticationConfig(),
            CommandBusInterface::class => [
                'command_map' => $this->getCommandMap(),
            ],
            'listeners'                => $this->getListeners(),
            UserInterface::class       => $this->getDefaultConfig(),
            AclInterface::class        => $this->getAclConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                UserRepositoryInterface::class => Repository\UserRepository::class,
                WithRowDataPrototypeInterface::class => Entity\User::class,
            ],
            'factories' => [
                // Registers the user factory under our own interface key.
                UserInterface::class                                => Container\UserFactory::class,
                Entity\User::class                                  => Entity\User::class,
                Admin\RequestHandler\CreateUserHandler::class       => Admin\RequestHandler\Container\CreateUserHandlerFactory::class,
                Admin\RequestHandler\UpdateUserHandler::class       => Admin\RequestHandler\Container\UpdateUserHandlerFactory::class,
                Admin\RequestHandler\ToggleUserActiveHandler::class => Admin\RequestHandler\Container\ToggleUserActiveHandlerFactory::class,
                CommandHandler\SaveUserHandler::class               => CommandHandler\Container\SaveUserHandlerFactory::class,
                Middleware\IdentityMiddleware::class                => Middleware\Container\IdentityMiddlewareFactory::class,
                Middleware\LoginMiddleware::class                   => Middleware\Container\LoginMiddlewareFactory::class,
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
                RegisterWidgetListener::class                   => RegisterWidgetListenerFactory::class,
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

    public function getListeners(): array
    {
        return [
            RegisterWidgetEvent::class => [
                ['listener' => RegisterWidgetListener::class, 'priority' => 1],
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
                'user' => [__DIR__ . '/../templates/user'],
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            Container\Configuration::ROUTE_SEGMENT_KEY           => Container\Configuration::ROUTE_SEGMENT_VALUE,
            Container\Configuration::ROUTE_NAME_PREFIX_KEY       => Container\Configuration::ROUTE_NAME_PREFIX_VALUE,
            Container\Configuration::ADMIN_ROUTE_SEGMENT_KEY     => Container\Configuration::ADMIN_ROUTE_SEGMENT_VALUE,
            Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_KEY => Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE,
            'login_path'                                         => '/' . Container\Configuration::ROUTE_SEGMENT_VALUE . '/login',
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

    public function getAuthenticationConfig(): array
    {
        return [
            'redirect'                                       => '/' . Container\Configuration::ROUTE_SEGMENT_VALUE . '/login',
            'username'                                       => 'email',
            'password'                                       => 'password',
            Container\Configuration::POST_LOGIN_REDIRECT_KEY => Container\Configuration::POST_LOGIN_REDIRECT_VALUE,
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'roles'     => [
                'Guest'  => [],
                'Member' => ['Guest'],
            ],
            'resources' => [
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.read'                                                            => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.create'                                                          => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.read'                                                           => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.create'                                                         => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'verify.email.read'                                                       => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.read'                                                => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.create'                                              => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'logout.read'                                                             => true,
                Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'account.read'                                                            => true,
                AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . rtrim(Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE, '.')       => true,
                AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'create'        => true,
                AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'update'        => true,
                AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'toggle.update' => true,
            ],
            'allow'     => [
                'Guest'         => [
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.read'               => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.create'             => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.read'              => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.create'            => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'verify.email.read'          => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.read'   => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.create' => [],
                ],
                'Member'        => [
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'logout.read' => [],
                ],
                'Administrator' => [
                    AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . rtrim(Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE, '.')       => [],
                    AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'create'        => [],
                    AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'update'        => [],
                    AdminConfiguration::ADMIN_ROUTE_NAME_PREFIX_VALUE . Container\Configuration::ADMIN_ROUTE_NAME_PREFIX_VALUE . 'toggle.update' => [],
                ],
            ],
            'deny'      => [
                'Member' => [
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.read'               => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'session.create'             => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.read'              => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'register.create'            => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'verify.email.read'          => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.read'   => [],
                    Container\Configuration::ROUTE_NAME_PREFIX_VALUE . 'resend.verification.create' => [],
                ],
            ],
        ];
    }
}
