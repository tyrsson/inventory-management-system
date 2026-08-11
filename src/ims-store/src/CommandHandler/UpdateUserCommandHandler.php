<?php

declare(strict_types=1);

/**
 * This file is part of the Ims Store package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Store\CommandHandler;

use DateTimeImmutable;
use Ims\Store\Entity\User;
use Override;
use Psl\Type;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\UserManager\Repository\UserRepositoryInterface;

final class UpdateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @throws Type\Exception\AssertException
     */
    #[Override]
    public function handle(CommandInterface $command): CommandResultInterface
    {
        $now = new DateTimeImmutable()->format('Y-m-d H:i:s');
        Type\intersection(
            Type\instance_of(User::class),
            Type\instance_of(CommandInterface::class),
        )->assert($command);
        $command = $command->withDetail('updatedAt', $now);
        return $this->users->save($command);
    }
}
