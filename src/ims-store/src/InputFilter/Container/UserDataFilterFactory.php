<?php

declare(strict_types=1);

namespace Ims\Store\InputFilter\Container;

use Ims\Store\InputFilter\UserDataFilter;
use Laminas\InputFilter\Factory;
use Psr\Container\ContainerInterface;

final class UserDataFilterFactory
{
    public function __invoke(ContainerInterface $container): UserDataFilter
    {
        return new UserDataFilter(
            $container->get(Factory::class),
        );
    }
}
