<?php

declare(strict_types=1);

namespace Webware\Acl\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter;
use Laminas\Validator;
use Webware\Core\InputFilter\SystemMessageTrait;

final class RoleDataFilter extends InputFilter\InputFilter
{
    use SystemMessageTrait;

    public function init(): void
    {
        $this->add([
            'name'        => 'id',
            'allow_empty' => true,
            'filters'     => [
                ['name' => Filter\ToInt::class],
                ['name' => Filter\ToNull::class],
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
            'name'              => 'parentId',
            'allow_empty'       => true,
            'continue_if_empty' => true,
            'required'          => false,
            'fallback_value'    => null,
            'filters'           => [
                ['name' => Filter\StringTrim::class],
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
        ]);
    }
}
