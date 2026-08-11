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

use Htmx\Middleware\DisableBodyMiddleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouteProviderInterface;
use Override;
use Webware\UserManager\Admin\RequestHandler\CreateUserHandler;
use Webware\UserManager\Admin\RequestHandler\ToggleUserActiveHandler;
use Webware\UserManager\Admin\RequestHandler\UpdateUserHandler;
use Webware\UserManager\Admin\RequestHandler\UpdateUserModalHandler;
use Webware\UserManager\Middleware\LoginMiddleware;
use Webware\UserManager\Middleware\ProcessToggleUserActiveMiddleware;
use Webware\UserManager\Middleware\ProcessUpdateUserMiddleware;
use Webware\UserManager\Middleware\RegistrationMiddleware;
use Webware\UserManager\RequestHandler\LoginHandler;
use Webware\UserManager\RequestHandler\LogoutHandler;
use Webware\UserManager\RequestHandler\RegistrationHandler;
use Webware\UserManager\RequestHandler\ResendVerificationHandler;
use Webware\UserManager\RequestHandler\UserListHandler;
use Webware\UserManager\RequestHandler\VerifyEmailHandler;

use function rtrim;

final readonly class RouteProvider implements RouteProviderInterface
{
    public function __construct(
        private string $routeSegment,
        private string $routeNamePrefix,
        private string $adminRouteSegment,
        private string $adminRouteNamePrefix,
    ) {}

    #[Override]
    public function registerRoutes(
        RouteCollectorInterface $routeCollector,
        MiddlewareFactoryInterface $middlewareFactory,
    ): void {
        // Login routes — AclMiddleware runs before Auth (login/register are guest grants)
        $routeCollector->get(
            '/' . $this->routeSegment . '/login',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                LoginHandler::class,
            ]),
            $this->routeNamePrefix . 'session.read',
        );

        $routeCollector->post(
            '/' . $this->routeSegment . '/login',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                LoginMiddleware::class,
                LoginHandler::class,
            ]),
            $this->routeNamePrefix . 'session.create',
        );

        // Registration routes
        $routeCollector->get(
            '/' . $this->routeSegment . '/register',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                RegistrationHandler::class,
            ]),
            $this->routeNamePrefix . 'register.read',
        );

        $routeCollector->post(
            '/' . $this->routeSegment . '/register',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                RegistrationMiddleware::class,
                RegistrationHandler::class,
            ]),
            $this->routeNamePrefix . 'register.create',
        );

        $routeCollector->get(
            '/' . $this->routeSegment . '/verify.email/{token}',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                VerifyEmailHandler::class,
            ]),
            $this->routeNamePrefix . 'verify.email.read',
        );

        $routeCollector->get(
            '/' . $this->routeSegment . '/resend.verification',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                ResendVerificationHandler::class,
            ]),
            $this->routeNamePrefix . 'resend.verification.read',
        );

        $routeCollector->post(
            '/' . $this->routeSegment . '/resend.verification',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                ResendVerificationHandler::class,
            ]),
            $this->routeNamePrefix . 'resend.verification.create',
        );

        $routeCollector->get(
            '/' . $this->routeSegment . '/logout',
            $middlewareFactory->prepare([
                LogoutHandler::class,
            ]),
            $this->routeNamePrefix . 'logout.read',
        )->setOptions([
            'navigation' => 'user',
            'label'      => 'Logout',
            'icon'       => 'bi-box-arrow-right',
            'parent'     => null,
            'order'      => 10,
        ]);

        // Admin
        $routeCollector->get(
            '/' . $this->adminRouteSegment,
            $middlewareFactory->prepare([
                UserListHandler::class,
            ]),
            rtrim($this->adminRouteNamePrefix, '.'),
        )->setOptions([
            'navigation' => 'admin',
            'label'      => 'Users',
            'icon'       => 'bi-people-fill',
            'parent'     => null,
            'order'      => 20,
        ]);

        $routeCollector->route(
            '/' . $this->adminRouteSegment . '/create',
            $middlewareFactory->prepare([
                CreateUserHandler::class,
            ]),
            ['GET', 'POST'],
            $this->adminRouteNamePrefix . 'create',
        )
            ->setOptions([
                'navigation' => 'admin',
                'label'      => 'Create User',
                'icon'       => 'bi-person-plus-fill',
                'parent'     => rtrim($this->adminRouteNamePrefix, '.'),
                'order'      => 10,
            ]);

        // Update the user (PATCH) — re-renders the user list (mirrors webware-acl's role.update route)
        $routeCollector->route(
            '/' . $this->adminRouteSegment . '/update/{id:\d+}',
            $middlewareFactory->prepare([
                BodyParamsMiddleware::class,
                ProcessUpdateUserMiddleware::class,
                UpdateUserHandler::class,
            ]),
            ['PATCH'],
            $this->adminRouteNamePrefix . 'update',
        );

        // Return the htmx modal for updating a user
        $routeCollector->route(
            '/' . $this->adminRouteSegment . '/update/{id:\d+}',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                UpdateUserModalHandler::class,
            ]),
            ['GET'],
            $this->adminRouteNamePrefix . 'update.modal',
        );

        $routeCollector->post(
            '/' . $this->adminRouteSegment . '/{id:\d+}/toggle',
            $middlewareFactory->prepare([
                DisableBodyMiddleware::class,
                ProcessToggleUserActiveMiddleware::class,
                ToggleUserActiveHandler::class,
            ]),
            $this->adminRouteNamePrefix . 'toggle.update',
        );
    }
}
