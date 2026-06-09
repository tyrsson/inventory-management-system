<?php

declare(strict_types=1);

namespace Webware\Acl\Entity;

use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;
use Webware\Acl\RuleType;
use Webware\ResultSet\WithRowDataPrototypeInterface;

use function json_decode;

final class Rule implements WithRowDataPrototypeInterface, ResourceInterface, RoleInterface
{
    public function __construct(
        public private(set) int|string|null $id       = null,
        public private(set) RuleType $type            = RuleType::Allow {
            set(string|RuleType $value) {
                $this->type = $this->resolveType($value);
            }
        },
        public private(set) ?string $roleId           = null,
        public private(set) ?string $resourceId       = null,
        public private(set) ?array $assertions        = null {
            set(?array $value) {
                $this->assertions = $value === null ? null : json_decode(json_encode($value), true);
            }
        },
        public private(set) ?string $parentResourceId = null,
    ) {}

    #[Override]
    public function exchangeArray(array $array): array
    {
        throw new \RuntimeException('Not implemented');
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

    public function withRowData(array $withRowData): static
    {
        return new self(...$withRowData);
    }

    public function toArray(): array
    {
        return (array) $this;
    }
}
