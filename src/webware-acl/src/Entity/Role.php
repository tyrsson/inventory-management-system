<?php

declare(strict_types=1);

namespace Webware\Acl\Entity;

final readonly class Role
{
    public function __construct(
        public string $roleId,
    ) {}
}
