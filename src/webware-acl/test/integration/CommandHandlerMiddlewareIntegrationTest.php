<?php

declare(strict_types=1);

namespace Webware\AclIntegrationTest;

use Laminas\Permissions\Acl\Role\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Acl\AclInterface;
use Webware\Acl\CommandBus\AuthorizableCommandInterface;
use Webware\Acl\CommandBus\CommandStatus;
use Webware\Acl\CommandBus\Middleware\CommandHandlerMiddleware;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus as UpstreamCommandStatus;
use Webware\CommandBus\CommandBus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandHandlerResolverInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\MiddlewarePipe;

#[CoversClass(CommandHandlerMiddleware::class)]
final class CommandHandlerMiddlewareIntegrationTest extends TestCase
{
    private function buildBus(
        CommandHandlerResolverInterface $resolver,
        AclInterface $acl,
    ): CommandBus {
        $pipe = new MiddlewarePipe();
        $pipe->pipe(new CommandHandlerMiddleware($resolver, $acl));
        return new CommandBus($pipe);
    }

    #[Test]
    public function allowedAuthorizableCommandDispatchesThroughPipeline(): void
    {
        $role    = $this->createStub(RoleInterface::class);
        $command = $this->createStub(AuthorizableCommandInterface::class);
        $command->method('getRole')->willReturn($role);
        $command->method('getPrivilegeId')->willReturn('create');

        $expectedResult = new CommandResult($command, UpstreamCommandStatus::Success, ['id' => 42]);

        $innerHandler = $this->createMock(CommandHandlerInterface::class);
        $innerHandler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->willReturn($innerHandler);

        $acl = $this->createMock(AclInterface::class);
        $acl->expects($this->once())
            ->method('isAllowed')
            ->with($role, $command, 'create')
            ->willReturn(true);

        $result = $this->buildBus($resolver, $acl)->handle($command);

        self::assertSame($expectedResult, $result);
        self::assertSame(UpstreamCommandStatus::Success, $result->getStatus());
    }

    #[Test]
    public function deniedAuthorizableCommandReturnsForbiddenWithoutCallingHandler(): void
    {
        $role    = $this->createStub(RoleInterface::class);
        $command = $this->createStub(AuthorizableCommandInterface::class);
        $command->method('getRole')->willReturn($role);
        $command->method('getPrivilegeId')->willReturn('delete');

        $resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $resolver->expects($this->never())->method('resolve');

        $acl = $this->createMock(AclInterface::class);
        $acl->expects($this->once())
            ->method('isAllowed')
            ->with($role, $command, 'delete')
            ->willReturn(false);

        $result = $this->buildBus($resolver, $acl)->handle($command);

        self::assertSame(CommandStatus::Forbidden, $result->getStatus());
        self::assertSame($command, $result->getCommand());
        self::assertNull($result->getResult());
    }

    #[Test]
    public function nonAuthorizableCommandDispatchesNormallyWithoutAclCheck(): void
    {
        $command        = $this->createStub(CommandInterface::class);
        $expectedResult = new CommandResult($command, UpstreamCommandStatus::Success, null);

        $innerHandler = $this->createMock(CommandHandlerInterface::class);
        $innerHandler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->willReturn($innerHandler);

        $acl = $this->createMock(AclInterface::class);
        $acl->expects($this->never())->method('isAllowed');

        $result = $this->buildBus($resolver, $acl)->handle($command);

        self::assertSame($expectedResult, $result);
    }
}
