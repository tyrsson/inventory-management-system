<?php

declare(strict_types=1);

use Htmx\Middleware\DetectAjaxRequestMiddleware;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Helper\ServerUrlMiddleware;
use Mezzio\Helper\UrlHelperMiddleware;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Axleus\Log\Middleware\MonologMiddleware;
use Mezzio\Session\SessionMiddleware;
use Psr\Container\ContainerInterface;
use Webware\Acl\Middleware\AclMiddleware;
use Webware\Acl\Middleware\AuthorizationMiddleware;
use Webware\UserManager\Middleware\IdentityMiddleware;
use Webware\Core\Middleware\AttachCoreServicesMiddleware;
use Webware\Event\Middleware\EventDispatcherMiddleware;
use Webware\Traccio\Middleware\TracyDebuggerMiddleware;

// Setup middleware pipeline:

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    // The error handler should be the first (most outer) middleware to catch
    // all Exceptions.
    class_exists(TracyDebuggerMiddleware::class) && $container->get('config')['debug']
        ? $app->pipe(TracyDebuggerMiddleware::class)
        : $app->pipe(ErrorHandler::class);

    $app->pipe(EventDispatcherMiddleware::class);
    $app->pipe(ServerUrlMiddleware::class);
    $app->pipe(SessionMiddleware::class);
    $app->pipe(IdentityMiddleware::class);
    $app->pipe(AttachCoreServicesMiddleware::class);
    $app->pipe(MonologMiddleware::class);
    $app->pipe(DetectAjaxRequestMiddleware::class);
    $app->pipe(\App\Middleware\ImsMessengerMiddleware::class);

    // Pipe more middleware here that you want to execute on every request:
    // - bootstrapping
    // - pre-conditions
    // - modifications to outgoing responses
    //
    // Piped Middleware may be either callables or service names. Middleware may
    // also be passed as an array; each item in the array must resolve to
    // middleware eventually (i.e., callable or service name).
    //
    // Middleware can be attached to specific paths, allowing you to mix and match
    // applications under a common domain.  The handlers in each middleware
    // attached this way will see a URI with the matched path segment removed.
    //
    // i.e., path of "/api/member/profile" only passes "/member/profile" to $apiMiddleware
    // - $app->pipe('/api', $apiMiddleware);
    // - $app->pipe('/docs', $apiDocMiddleware);
    // - $app->pipe('/files', $filesMiddleware);

    // Register the routing middleware in the middleware pipeline.
    // This middleware registers the Mezzio\Router\RouteResult request attribute.
    $app->pipe(RouteMiddleware::class);

    // The following handle routing failures for common conditions:
    // - HEAD request but no routes answer that method
    // - OPTIONS request but no routes answer that method
    // - method not allowed
    // Order here matters; the MethodNotAllowedMiddleware should be placed
    // after the Implicit*Middleware.
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);

    // Seed the UrlHelper with the routing results:
    $app->pipe(UrlHelperMiddleware::class);
    // Inject roles + active route name into the Navigation view helper:
    $app->pipe(\Webware\Navigation\Middleware\NavigationMiddleware::class);

    // ACL route access check — must run after routing and identity are resolved,
    // before dispatch.
    $app->pipe(AclMiddleware::class);
    $app->pipe(AuthorizationMiddleware::class);

    // Register the dispatch middleware in the middleware pipeline
    $app->pipe(DispatchMiddleware::class);

    // At this point, if no Response is returned by any middleware, the
    // NotFoundHandler kicks in; alternately, you can provide other fallback
    // middleware to execute.
    $app->pipe(NotFoundHandler::class);
};
