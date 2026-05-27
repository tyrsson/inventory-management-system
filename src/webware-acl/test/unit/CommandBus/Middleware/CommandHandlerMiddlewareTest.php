<?php

declare(strict_types=1);

namespace Webware\AclTest\CommandBus\Middleware;

use Laminas\Permissions\Acl\Role\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Acl\AclInterface;
use Webware\Acl\CommandBus\AuthorizableCommandInterface;
use Webware\Acl\CommandBus\CommandStatus;
use Webware\Acl\CommandBus\Middleware\CommandHandlerMiddleware;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandHandlerResolverInterface;
use Webware\CommandBus\CommandInterface;

#[CoversClass(CommandHandlerMiddleware::class)]
final class CommandHandlerMiddlewareTest extends TestCase
{
    private function makeMiddleware(
        CommandHandlerResolverInterface $resolver,
        AclInterface $acl,
    ): CommandHandlerMiddleware {
        return new CommandHandlerMiddleware($resolver, $acl);
    }

    /** Satisfies the MiddlewareInterface $handler parameter; never called by terminal middleware. */
    private function unusedHandler(): CommandHandlerInterface
    {
        return $this->createStub(CommandHandlerInterface::class);
    }

    #[Test]
    public function nonAuthorizableCommandPassesThroughWithoutAclCheck(): void
    {
        $command         = $this->createStub(CommandInterface::class);
        $expectedResult  = $this->createStub(CommandResultInterface::class);

        $innerHandler = $this->createMock(CommandHandlerInterface::class);
        $innerHandler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($command)
            ->willReturn($innerHandler);

        $acl = $this->createMock(AclInterface::class);
        $acl->expects($this->never())->method('isAllowed');

        $result = $this->makeMiddleware($resolver, $acl)
            ->process($command, $this->unusedHandler());

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function authorizableCommandAllowedByAclResolvesAndHandles(): void
    {
        $role    = $this->createStub(RoleInterface::class);
        $command = $this->createStub(AuthorizableCommandInterface::class);
        $command->method('getRole')->willReturn($role);
        $command->method('getPrivilegeId')->willReturn('create');

        $expectedResult = $this->createStub(CommandResultInterface::class);

        $innerHandler = $this->createMock(CommandHandlerInterface::class);
        $innerHandler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($expectedResult);

        $resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($command)
            ->willReturn($innerHandler);

        $acl = $this->createMock(AclInterface::class);
        $acl->expects($this->once())
            ->method('isAllowed')
            ->with($role, $command, 'create')
            ->willReturn(true);

        $result = $this->makeMiddleware($resolver, $acl)
            ->process($command, $this->unusedHandler());

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function authorizableCommandDeniedByAclReturnsForbiddenResult(): void
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

        $result = $this->makeMiddleware($resolver, $acl)
            ->process($command, $this->unusedHandler());

        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Forbidden, $result->getStatus());
        self::assertSame($command, $result->getCommand());
        self::assertNull($result->getResult());
    }
}
