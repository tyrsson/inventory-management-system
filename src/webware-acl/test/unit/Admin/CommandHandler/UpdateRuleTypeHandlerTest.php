<?php

declare(strict_types=1);

namespace Webware\AclTest\Admin\CommandHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Acl\Admin\Command\UpdateRuleTypeCommand;
use Webware\Acl\Admin\CommandHandler\UpdateRuleTypeHandler;
use Webware\Acl\Repository\AclRepositoryInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

#[CoversClass(UpdateRuleTypeHandler::class)]
final class UpdateRuleTypeHandlerTest extends TestCase
{
    #[Test]
    public function handleUpdatesRuleTypeIncrementsVersionAndReturnsSuccess(): void
    {
        $repo = $this->createMock(AclRepositoryInterface::class);
        $repo->expects($this->once())->method('updateRuleType')->with(9, 'deny');
        $repo->expects($this->once())->method('incrementVersion');

        $command = new UpdateRuleTypeCommand(9, 'deny');
        $result  = (new UpdateRuleTypeHandler($repo))->handle($command);

        self::assertInstanceOf(CommandResult::class, $result);
        self::assertSame(CommandStatus::Success, $result->getStatus());
        self::assertNull($result->getResult());
    }
}
