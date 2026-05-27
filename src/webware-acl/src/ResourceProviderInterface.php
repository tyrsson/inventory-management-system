<?php

declare(strict_types=1);

namespace Webware\Acl;

use Laminas\Permissions\Acl\Resource\ResourceInterface;

interface ResourceProviderInterface
{
    public function getResource(): ResourceInterface;
}
