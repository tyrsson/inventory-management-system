<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter;
use Laminas\Validator;
use Webware\Acl\RuleType;
use Webware\Acl\Validator\Assertion;

final class RuleDataFilter extends InputFilter\InputFilter
{
    public function __construct(
        protected readonly InputFilter\Factory $factory
    ) {}

    public function init():  void
    {
        $this->add([
            'name'     => 'resourceId',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);

        $this->add([
            'name' => 'type',
            'required' => true,
            'filters'  => [
                [
                    'name' => Filter\ToEnum::class,
                    'options' => [
                        'enum' => RuleType::class,
                    ],
                ],
            ],
        ]);

        $this->add([
            'name'     => 'roleId',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);

        $this->add([
            'name'     => 'grantMode',
            'required' => true,
            'filters'  => [
                [
                    'name' => Filter\AllowList::class,
                    'options' => [
                        'explicit',
                        'inherited',
                    ],
                ],
            ],
        ]);

        $this->add([
            'name'              => 'assertions',
            'allow_empty'       => true,
            'continue_if_empty' => true,
            'required'          => true,
            'fallback_value'    => null,
            'filters'           => [
                ['name' => Filter\ToNull::class],
            ],
            'validators'        => [
                [
                    'name' => Assertion::class,
                    'options' => [
                        'nullable' => true,
                    ],
                ],
            ],
        ]);
    }
}
