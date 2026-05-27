<?php

declare(strict_types=1);

namespace Webware\Event\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class EventDispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request->withAttribute(
                EventDispatcherInterface::class, 
                $this->eventDispatcher
            )
        );
    }
}
