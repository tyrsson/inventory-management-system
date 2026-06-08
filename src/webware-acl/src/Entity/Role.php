<?php

declare(strict_types=1);

namespace Webware\Acl\Entity;

use Laminas\Permissions\Acl\Role\RoleInterface;
use PhpDb\ResultSet\RowPrototypeInterface;

final class Role implements RoleInterface, RowPrototypeInterface
{
    public function __construct(
        public private(set) int|string|null $id     = null,
        public private(set) int|string|null $roleId = null,
        /** @var RoleInterface[]|string[]|null The parent role identifiers. */
        public private(set) ?array $parentId        = null,
    ) {}

    public function getRoleId(): int|string|null
    {
        return $this->roleId;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getParentId(): ?array
    {
        return $this->parentId;
    }

    public function exchangeArray(array $data): array
    {
        $this->id       = $data['id'];
        $this->roleId   = $data['roleId'];
        $parents        = json_decode($data['parentId'], true) ?? [];
        $this->parentId = array_map(
            static fn (string $id) => new self(roleId: $id),
            $parents
        );

        return (array) $this;
    }
}
