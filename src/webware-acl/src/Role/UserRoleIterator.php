<?php

declare(strict_types=1);

namespace Webware\Acl\Role;

use Iterator;
use Webware\UserManager\UserInterface;

/**
 * @implements Iterator<int, UserInterface>
 */
final class UserRoleIterator implements Iterator
{
    private array $roles;

    private int $position = 0;

    public function __construct(private readonly UserInterface $user)
    {
        $this->roles = [...$user->getRoles()];
    }

    public function current(): UserInterface
    {
        return new SingleRoleUserProxy($this->user, $this->roles[$this->position]);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->roles[$this->position]);
    }
}
