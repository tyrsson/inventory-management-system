<?php

declare(strict_types=1);

namespace Webware\Admin\Middleware;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\Admin\Event\RegisterWidgetEvent;
use Webware\Admin\Widget\AclWidgetFilterIterator;
use Webware\UserManager\UserInterface;

/**
 * Dispatches RegisterWidgetEvent so that modules may contribute
 * their widgets, then filters the collected widgets through the ACL using
 * the current user's roles, and attaches the filtered iterator to the request.
 *
 * Route this middleware in the admin dashboard route pipeline, after
 * IdentityMiddleware (so the user attribute is already set).
 */
final class DashboardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AclInterface $acl,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RegisterWidgetEvent $event */
        $event = $this->dispatcher->dispatch(new RegisterWidgetEvent());

        $user    = $request->getAttribute(UserInterface::class);
        $widgets = new AclWidgetFilterIterator($event->getIterator(), $this->acl, $user);

        return $handler->handle(
            $request->withAttribute(RegisterWidgetEvent::class, $widgets),
        );
    }
}
