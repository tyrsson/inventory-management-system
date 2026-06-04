<?php

declare(strict_types=1);

namespace Webware\Acl\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;

final class AclMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AclInterface $acl,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request->withAttribute(AclInterface::class, $this->acl)
        );
    }
}
