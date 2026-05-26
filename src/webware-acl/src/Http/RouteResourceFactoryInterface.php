<?php

declare(strict_types=1);

namespace Webware\Acl\Http;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;

interface RouteResourceFactoryInterface
{
    public function __invoke(
        RouteResult $routeResult,
        ServerRequestInterface $request,
    ): RouteResourceInterface;
}
