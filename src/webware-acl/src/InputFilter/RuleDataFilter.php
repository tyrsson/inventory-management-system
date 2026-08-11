<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter;
use Webware\Acl\RuleType;
use Webware\Acl\Validator\Assertion;
use Webware\Core\InputFilter\SystemMessageTrait;

final class RuleDataFilter extends InputFilter\InputFilter
{
    use SystemMessageTrait;

    public function __construct(
        protected readonly InputFilter\Factory $factory,
    ) {}

    public function init(): void
    {
        $this->add([
            'name'     => 'resourceId',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);

        $this->add([
            'name'     => 'type',
            'required' => true,
            'filters'  => [
                [
                    'name'    => Filter\ToEnum::class,
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
            'name'              => 'assertions',
            'allow_empty'       => true,
            'continue_if_empty' => true,
            'required'          => false,
            'fallback_value'    => null,
            'filters'           => [
                ['name' => Filter\ToNull::class],
                [
                    'name'    => Filter\Callback::class,
                    'options' => [
                        'callback' => static function ($value) {
                            if (is_string($value)) {
                                return [$value];
                            }

                            return $value;
                        },
                    ],
                ],
            ],
            'validators'        => [
                [
                    'name'    => Assertion::class,
                    'options' => [
                        'nullable' => true,
                    ],
                ],
            ],
        ]);
    }
}
