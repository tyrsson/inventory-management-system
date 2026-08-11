<?php

declare(strict_types=1);

namespace Webware\UserManager\Event;

use Webware\Event\Event;
use Webware\UserManager\Command\CreateUserCommand;
use Webware\UserManager\Command\UpdateUserCommand;

final class SendVerificationEmailEvent extends Event
{
    public function __construct(
        public readonly CreateUserCommand|UpdateUserCommand $target,
    ) {}

    public function getEmail(): string
    {
        return $this->target->email;
    }

    public function getTarget(): CreateUserCommand|UpdateUserCommand
    {
        return $this->target;
    }

    public function getToken(): string
    {
        return $this->target->verificationToken;
    }
}
