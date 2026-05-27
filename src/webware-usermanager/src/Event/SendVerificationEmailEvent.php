<?php

declare(strict_types=1);

namespace Webware\UserManager\Event;

use Webware\Event\Event;
use Webware\UserManager\Command\SaveUserCommand;

final class SendVerificationEmailEvent extends Event
{
    public function __construct(
        public readonly SaveUserCommand $target,
        public readonly string $verificationToken,
    ) {}

    public function getEmail(): string
    {
        return $this->target->email;
    }

    public function getToken(): string
    {
        return $this->verificationToken;
    }

    public function getCommand(): SaveUserCommand
    {
        return $this->target;
    }
}
