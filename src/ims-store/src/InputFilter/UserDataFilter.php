<?php

declare(strict_types=1);

namespace Ims\Store\InputFilter;

use Laminas\Filter;
use Laminas\InputFilter;
use Override;
use Webware\UserManager\InputFilter\UserDataFilter as WebwareUserDataFilter;

final class UserDataFilter extends WebwareUserDataFilter
{
    #[Override]
    public function init(): void
    {
        parent::init();

        $this->add([
            'type'    => InputFilter\InputFilter::class,
            'storeId' => [
                'name'     => 'storeId',
                'required' => true,
                'filters'  => [
                    ['name' => Filter\ToInt::class],
                ],
            ],
        ], 'details');
    }
}
