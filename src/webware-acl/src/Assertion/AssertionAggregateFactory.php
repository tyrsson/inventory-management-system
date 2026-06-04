<?php

declare(strict_types=1);

namespace Webware\Acl\Assertion;

use Webware\Acl\AssertionManager;

final class AssertionAggregateFactory
{
    public function __construct(
        private readonly AssertionManager $assertionManager,
    ) {}

    public function __invoke(?array $aliasesOrClassnames): ?AssertionAggregate
    {
        if (empty($aliasesOrClassnames)) {
            return null;
        }

        $aggregate = new AssertionAggregate();
        $aggregate->setAssertionManager($this->assertionManager);
        $aggregate->setMode(AssertionAggregate::MODE_AT_LEAST_ONE);
        $aggregate->addAssertions($aliasesOrClassnames);

        return $aggregate;
    }
}
