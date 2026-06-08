<?php

declare(strict_types=1);

namespace Webware\Acl\Entity;

use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;
use PhpDb\ResultSet\RowPrototypeInterface;
use Webware\Acl\RuleType;

use function json_decode;

final class Rule implements RowPrototypeInterface, ResourceInterface, RoleInterface
{
    public function __construct(
        public private(set) int|string|null $id       = null,
        public private(set) RuleType $type            = RuleType::Allow,
        public private(set) ?string $roleId           = null,
        public private(set) ?string $resourceId       = null,
        public private(set) ?array $assertions        = null,
        public private(set) ?string $parentResourceId = null,
    ) {}

    #[Override]
    public function exchangeArray(array $array): array
    {
        $this->id               = $array['id'];
        $this->type             = $this->resolveType($array['type']);
        $this->roleId           = $array['roleId'];
        $this->resourceId       = $array['resourceId'];
        $this->assertions       = json_decode($array['assertions'], true);
        $this->parentResourceId = $array['parentResourceId'];

        return (array) $this;
    }

    #[Override]
    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    #[Override]
    public function getRoleId(): ?string
    {
        return $this->roleId;
    }
    
    private function resolveType(string|RuleType $type): RuleType
    {
        if ($type instanceof RuleType) {
            return $type;
        }

        return RuleType::from($type);
    }
}
