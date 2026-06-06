<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter\Container;

use Laminas\InputFilter;
use Psr\Container\ContainerInterface;
use Webware\Acl\InputFilter\RuleDataFilter;

final readonly class RuleDataFilterFactory
{
    public function __invoke(ContainerInterface $container): RuleDataFilter
    {
        return new RuleDataFilter(
            $container->get(InputFilter\Factory::class)
        );
    }
}
