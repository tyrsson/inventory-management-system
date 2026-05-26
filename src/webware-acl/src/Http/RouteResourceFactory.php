<?php

declare(strict_types=1);

namespace Webware\Acl\Http;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;

final class RouteResourceFactory implements RouteResourceFactoryInterface
{
    public function __construct(
        private readonly array $paramMap = [],
    ) {}

    public function __invoke(
        RouteResult $routeResult,
        ServerRequestInterface $request,
    ): RouteResourceInterface {
        return new RouteResource($routeResult, $request, $this->paramMap);
    }
}
