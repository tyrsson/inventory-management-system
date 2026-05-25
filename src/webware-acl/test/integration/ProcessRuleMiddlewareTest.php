<?php

declare(strict_types=1);

namespace Webware\AclIntegrationTest;

use Axleus\Message\SystemMessengerInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\Admin\Command\SaveRuleCommand;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\Admin\Middleware\ProcessRuleMiddleware;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandBusInterface;

#[CoversClass(ProcessRuleMiddleware::class)]
final class ProcessRuleMiddlewareTest extends TestCase
{
    private function capturingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $received = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->received = $request;
                return new EmptyResponse();
            }
        };
    }

    #[Test]
    public function postWithValidBodyDispatchesSaveRuleCommandAndSetsSuccess(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(SaveRuleCommand::class))
            ->willReturnCallback(fn($cmd) => new CommandResult($cmd, CommandStatus::Success, null));

        $messenger = $this->createStub(SystemMessengerInterface::class);

        $request = (new ServerRequest([], [], '/', 'POST'))
            ->withParsedBody(['role_pk' => '1', 'resource_pk' => '2', 'privilege_pk' => '3', 'type' => 'allow'])
            ->withAttribute(SystemMessengerInterface::class, $messenger);

        $handler    = $this->capturingHandler();
        $middleware = new ProcessRuleMiddleware($bus);
        $middleware->process($request, $handler);

        $result = $handler->received?->getAttribute(CommandResult::class);
        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Success, $result->getStatus());
    }

    #[Test]
    public function postWithMissingRolePkSetsFailure(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);

        $request = (new ServerRequest([], [], '/', 'POST'))
            ->withParsedBody(['resource_pk' => '2', 'privilege_pk' => '3']);

        $handler    = $this->capturingHandler();
        $middleware = new ProcessRuleMiddleware($bus);
        $middleware->process($request, $handler);

        $result = $handler->received?->getAttribute(CommandResult::class);
        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Failure, $result->getStatus());
    }

    #[Test]
    public function patchWithValidBodyDispatchesUpdateRuleTypeCommandAndSetsSuccess(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(UpdateRuleTypeCommand::class))
            ->willReturnCallback(fn($cmd) => new CommandResult($cmd, CommandStatus::Success, null));

        $messenger = $this->createStub(SystemMessengerInterface::class);

        $request = (new ServerRequest([], [], '/', 'PATCH'))
            ->withAttribute('id', '7')
            ->withParsedBody(['type' => 'deny'])
            ->withAttribute(SystemMessengerInterface::class, $messenger);

        $handler    = $this->capturingHandler();
        $middleware = new ProcessRuleMiddleware($bus);
        $middleware->processPatch($request, $handler);

        $result = $handler->received?->getAttribute(CommandResult::class);
        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Success, $result->getStatus());
    }
}
