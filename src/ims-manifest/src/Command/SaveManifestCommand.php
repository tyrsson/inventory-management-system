<?php

declare(strict_types=1);

namespace Ims\Manifest\Command;

use Ims\Store\Acl\StoreOwnedResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Override;
use Webware\Acl\RoleProviderInterface;
use Webware\CommandBus\CommandInterface;
use Webware\UserManager\UserInterface;

final class SaveManifestCommand implements CommandInterface, RoleProviderInterface, StoreOwnedResourceInterface
{
    public function __construct(
        public readonly string $resourceId,
        public readonly int $storeId,
        public readonly UserInterface $user,
    ) {}

    #[Override]
    public function getRole(): RoleInterface
    {
        return $this->user;
    }

    #[Override]
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    #[Override]
    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
