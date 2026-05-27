<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Manifest\CommandHandler;

use Ims\Manifest\Command\UploadManifestCommand;
use Ims\Manifest\Repository\ManifestRepositoryInterface;
use Override;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;

final class UploadManifestHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ManifestRepositoryInterface $manifests,
    ) {}

    #[Override]
    public function handle(CommandInterface $command): CommandResult
    {
        assert($command instanceof UploadManifestCommand);

        $manifestId = $this->manifests->insertFromCsv($command->parsed, $command->userId, $command->csvPath);

        return new CommandResult($command, CommandStatus::Success, $manifestId);
    }
}
