<?php

declare(strict_types=1);

namespace Webware\UserManager\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter;
use Laminas\Validator;
use Webware\Core\InputFilter\SystemMessageTrait;

use function is_string;

class UserDataFilter extends InputFilter\InputFilter
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
            'name'     => 'firstName',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);

        $this->add([
            'name'     => 'lastName',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
            ],
        ]);

        $this->add([
            'name'       => 'email',
            'required'   => true,
            'filters'    => [
                ['name' => Filter\StringTrim::class],
            ],
            'validators' => [
                ['name' => Validator\EmailAddress::class],
            ],
        ]);

        $this->add([
            'name'     => 'passwordHash',
            'required' => true,
        ]);

        $this->add([
            'name'       => 'confirmPasswordHash',
            'required'   => true,
            'validators' => [
                [
                    'name'    => Validator\Identical::class,
                    'options' => [
                        'token'    => 'passwordHash',
                        'messages' => [
                            Validator\Identical::NOT_SAME      => 'Passwords do not match.',
                            Validator\Identical::MISSING_TOKEN => 'Please enter your password.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->add([
            'name'        => 'verificationToken',
            'allow_empty' => true,
            'validators'  => [
                ['name' => Validator\Uuid::class],
            ],
        ]);

        $this->add([
            'name'     => 'roleId',
            'required' => true,
            'filters'  => [
                ['name' => Filter\StringTrim::class],
                // [
                //     'name'    => Filter\Callback::class,
                //     'options' => [
                //         'callback' => static function ($value) {
                //             if (is_string($value)) {
                //                 return [$value];
                //             }
                //             return $value;
                //         },
                //     ],
                // ],
            ],
        ]);

        $this->add([
            'name'              => 'active',
            'allow_empty'       => true,
            'continue_if_empty' => true,
            'required'          => false,
            'fallback_value'    => false,
            'filters'           => [
                ['name' => Filter\Boolean::class],
            ],
        ]);
    }
}
