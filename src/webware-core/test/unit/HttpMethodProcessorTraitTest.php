<?php

declare(strict_types=1);

namespace Webware\CoreTest;

use DomainException;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Core\HttpMethodProcessorTrait;

#[CoversClass(HttpMethodProcessorTrait::class)]
final class HttpMethodProcessorTraitTest extends TestCase
{
    private MiddlewareInterface $middleware;

    protected function setUp(): void
    {
        $this->middleware = new class() implements MiddlewareInterface {
            use HttpMethodProcessorTrait;

            /** @var string[] */
            public array $called = [];

            public function processGet(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->called[] = 'GET';

                return $handler->handle($request);
            }

            public function processPost(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->called[] = 'POST';

                return $handler->handle($request);
            }

            public function processPatch(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->called[] = 'PATCH';

                return $handler->handle($request);
            }

            public function processDelete(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->called[] = 'DELETE';

                return $handler->handle($request);
            }
        };
    }

    /** @return array<string, array{string, string}> */
    public static function verbProvider(): array
    {
        return [
            'GET dispatches to processGet'       => ['GET', 'GET'],
            'POST dispatches to processPost'     => ['POST', 'POST'],
            'PATCH dispatches to processPatch'   => ['PATCH', 'PATCH'],
            'PUT dispatches to processPatch'     => ['PUT', 'PATCH'],
            'DELETE dispatches to processDelete' => ['DELETE', 'DELETE'],
        ];
    }

    #[Test]
    #[DataProvider('verbProvider')]
    public function itDispatchesToCorrectMethod(string $httpMethod, string $expectedMethod): void
    {
        $request = new ServerRequest([], [], '/', $httpMethod);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new EmptyResponse());

        $this->middleware->process($request, $handler);

        self::assertSame([$expectedMethod], $this->middleware->called);
    }

    #[Test]
    public function itThrowsDomainExceptionOnUnknownMethod(): void
    {
        $request = new ServerRequest([], [], '/', 'TRACE');
        $handler = $this->createStub(RequestHandlerInterface::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: TRACE');

        $this->middleware->process($request, $handler);
    }

    #[Test]
    public function defaultPassThroughCallsHandler(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            use HttpMethodProcessorTrait;
        };

        $expectedResponse = new EmptyResponse();
        $request          = new ServerRequest([], [], '/', 'GET');
        $handler          = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $response = $middleware->process($request, $handler);

        self::assertSame($expectedResponse, $response);
    }
}
