<?php

declare(strict_types=1);

namespace Webware\Core;

use DomainException;
use Fig\Http\Message\RequestMethodInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Trait to process HTTP methods in a MiddlewareInterface.
 * Override only the verb methods your middleware needs to handle;
 * unhandled verbs pass through to the next handler by default.
 */
trait HttpMethodProcessorTrait
{
    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return match ($request->getMethod()) {
            RequestMethodInterface::METHOD_GET    => $this->processGet($request, $handler),
            RequestMethodInterface::METHOD_POST   => $this->processPost($request, $handler),
            RequestMethodInterface::METHOD_PATCH,
            RequestMethodInterface::METHOD_PUT    => $this->processPatch($request, $handler),
            RequestMethodInterface::METHOD_DELETE => $this->processDelete($request, $handler),
            default                               => throw new DomainException('Unsupported HTTP method: ' . $request->getMethod()),
        };
    }

    public function processGet(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }

    public function processPost(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }

    public function processPatch(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }

    public function processDelete(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }
}
