<?php

declare(strict_types=1);

namespace Webware\Acl\Http;

use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Webware\Acl\RoleProviderInterface;

interface RouteResourceInterface extends ResourceInterface, RoleProviderInterface {}
