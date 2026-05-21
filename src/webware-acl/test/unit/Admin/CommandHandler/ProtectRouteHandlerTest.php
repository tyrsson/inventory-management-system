<?php

declare(strict_types=1);

namespace Webware\AclTest\Admin\CommandHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\Acl\Admin\Command\ProtectRouteCommand;
use Webware\Acl\Admin\CommandHandler\ProtectRouteHandler;
use Webware\Acl\Cache\AclCacheInterface;
use Webware\Acl\Entity\Role;
use Webware\Acl\Repository\AclRepositoryInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

#[CoversClass(ProtectRouteHandler::class)]
final class ProtectRouteHandlerTest extends TestCase
{
    #[Test]
    public function handleProtectsRouteWithNoRolesAndCommits(): void
    {
        $repo = $this->createMock(AclRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->expects($this->once())
            ->method('saveResource')
            ->with('user.list', 'user.list')
            ->willReturn(3);
        $repo->expects($this->once())
            ->method('insertPrivilege')
            ->with(3, 'read', 'Read')
            ->willReturn(7);
        $repo->expects($this->never())->method('saveRule');
        $repo->expects($this->once())->method('incrementVersion');
        $repo->expects($this->once())->method('commit');
        $repo->expects($this->never())->method('rollback');

        $cache = $this->createMock(AclCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $command = new ProtectRouteCommand('user.list', ['GET'], []);
        $result  = (new ProtectRouteHandler($repo, $cache))->handle($command);

        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Success, $result->getStatus());
        self::assertSame(3, $result->getResult());
    }

    #[Test]
    public function handleCreatesRulesForRolesFromWarmCache(): void
    {
        $repo = $this->createMock(AclRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->method('saveResource')->willReturn(4);
        $repo->method('insertPrivilege')->willReturn(9);
        $repo->expects($this->once())
            ->method('saveRule')
            ->with(2, 4, 9, 'allow');
        $repo->expects($this->once())->method('incrementVersion');
        $repo->expects($this->once())->method('commit');
        $repo->expects($this->never())->method('rollback');
        $repo->expects($this->never())->method('fetchRoles');

        $cache = $this->createStub(AclCacheInterface::class);
        $cache->method('get')->willReturn([
            'version' => 1,
            'roles'   => [
                ['id' => 2, 'role_id' => 'admin'],
            ],
        ]);

        $command = new ProtectRouteCommand('product.create', ['POST'], ['admin']);
        $result  = (new ProtectRouteHandler($repo, $cache))->handle($command);

        self::assertSame(CommandStatus::Success, $result->getStatus());
    }

    #[Test]
    public function handleFallsBackToFetchRolesWhenCacheIsNull(): void
    {
        $repo = $this->createMock(AclRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->method('saveResource')->willReturn(5);
        $repo->method('insertPrivilege')->willReturn(11);
        $repo->expects($this->once())
            ->method('fetchRoles')
            ->willReturn([1 => new Role(1, 'editor')]);
        $repo->expects($this->once())
            ->method('saveRule')
            ->with(1, 5, 11, 'allow');
        $repo->expects($this->once())->method('incrementVersion');
        $repo->expects($this->once())->method('commit');
        $repo->expects($this->never())->method('rollback');

        $cache = $this->createStub(AclCacheInterface::class);
        $cache->method('get')->willReturn(null);

        $command = new ProtectRouteCommand('manifest.edit', ['PUT'], ['editor']);
        $result  = (new ProtectRouteHandler($repo, $cache))->handle($command);

        self::assertSame(CommandStatus::Success, $result->getStatus());
    }

    #[Test]
    public function handleRollsBackAndRethrowsOnException(): void
    {
        $repo = $this->createMock(AclRepositoryInterface::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->method('saveResource')->willThrowException(new RuntimeException('DB error'));
        $repo->expects($this->never())->method('commit');
        $repo->expects($this->once())->method('rollback');

        $cache = $this->createStub(AclCacheInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        (new ProtectRouteHandler($repo, $cache))->handle(
            new ProtectRouteCommand('some.route', ['GET'], [])
        );
    }
}
