<?php

declare(strict_types=1);

namespace Webware\Acl\Assertion;

use Laminas\Permissions\Acl\Assertion\AssertionAggregate as BaseAggregate;
use Laminas\Permissions\Acl\Assertion\AssertionManager as BaseManager;
use Webware\Acl\AssertionManager;

final class AssertionAggregate extends BaseAggregate
{
    public function setAssertionManager(BaseManager|AssertionManager $manager): AssertionAggregate
    {
        $this->assertionManager = $manager;

        return $this;
    }

    public function getAssertionManager(): ?AssertionManager
    {
        return $this->assertionManager;
    }
}
