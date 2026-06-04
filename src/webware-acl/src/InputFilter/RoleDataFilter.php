<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter;

use Laminas\InputFilter;
use Laminas\Filter;
use Laminas\Validator;

final class RoleDataFilter extends InputFilter\InputFilter
{
    public function init(): void
    {
        $this->add([
            'name' => 'id',
            'allow_empty' => true,
            'filters' => [
                ['name' => Filter\ToInt::class],
                ['name' => Filter\ToNull::class],
            ],
        ]);

        $this->add([
            'name'     => 'name',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);
    }
}
