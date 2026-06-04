<?php

declare(strict_types=1);

namespace Webware\Core\Middleware;

use Laminas\InputFilter\InputFilterPluginManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AttachCoreServicesMiddleware implements MiddlewareInterface
{
    public function __construct(
        private InputFilterPluginManager $inputFilterPluginManager,
    ) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request
                ->withAttribute(
                    InputFilterPluginManager::class,
                    $this->inputFilterPluginManager
            )
        );
    }
}
