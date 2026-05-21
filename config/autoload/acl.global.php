<?php

declare(strict_types=1);

use Webware\Acl\AclInterface;

return [
    AclInterface::class => [
        'roles'     => [],
        'resources' => [],
        'allow'     => [],
        'deny'      => [],
    ],
];
