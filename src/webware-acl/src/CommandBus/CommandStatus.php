<?php

declare(strict_types=1);

namespace Webware\Acl\CommandBus;

use Webware\CommandBus\Command\CommandStatusInterface;

enum CommandStatus implements CommandStatusInterface
{
    case Success;
    case Failure;
    case Forbidden;
}
