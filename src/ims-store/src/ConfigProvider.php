<?php

declare(strict_types=1);

namespace Ims\Store;

use Webware\Acl\AclInterface;
use Webware\Acl\AssertionManager;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            AclInterface::class     => $this->getAclConfig(),
            AssertionManager::class => $this->getAssertionManagerConfig(),
        ];
    }

    public function getAclConfig(): array
    {
        return [
            'roles'     => [
                'Warehouse'            => ['Member'],
                'Sales'                => ['Member'],
                'Collections'          => ['Member'],
                'Warehouse Supervisor' => ['Warehouse'],
                'Assistant Manager'    => ['Sales', 'Warehouse', 'Collections'],
                'Manager'              => ['Assistant Manager', 'Warehouse Supervisor'],
                'Administrator'        => ['Manager'],
            ],
            'resources' => [],
            'allow'     => [],
        ];
    }

    public function getAssertionManagerConfig(): array
    {
        return [
            'aliases'   => [
                'Store Owned Resource' => Acl\StoreOwnedResourceAssertion::class,
            ],
            'factories' => [
                Acl\StoreOwnedResourceAssertion::class => Acl\StoreOwnedResourceAssertion::class,
            ],
        ];
    }
}
