<?php

declare(strict_types=1);

namespace Ims\Store;

use Webware\Acl\AclInterface;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            AclInterface::class => $this->getAclConfig(),
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
}

