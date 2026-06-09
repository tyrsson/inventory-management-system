<?php

declare(strict_types=1);

namespace Webware\ResultSet;

use ArrayObject;
use Override;
use PhpDb\ResultSet\RowPrototypeInterface;
use PhpDb\ResultSet\AbstractResultSet;
use PhpDb\ResultSet\ResultSetInterface;

final class WithRowDataResultSet extends AbstractResultSet
{
    public function __construct(
        private WithRowDataPrototypeInterface $rowPrototype
    ) {}
    /**
     * Iterator: get current item
     */
    #[Override]
    public function current(): array|ArrayObject|WithRowDataPrototypeInterface|null
    {
        $data = parent::current();

        if (is_array($data)) {
            return $this->getRowPrototype()->withRowData($data);
        }

        return $data;
    }

    #[Override]
    public function setRowPrototype(
        ArrayObject|RowPrototypeInterface|WithRowDataPrototypeInterface $rowPrototype
    ): ResultSetInterface {
        if (! $rowPrototype instanceof WithRowDataPrototypeInterface) {
            throw new \InvalidArgumentException(
                'Row prototype must implement ' . WithRowDataPrototypeInterface::class
            );
        }

        $this->rowPrototype = $rowPrototype;

        return $this;
    }

    #[Override]
    public function getRowPrototype(): WithRowDataPrototypeInterface
    {
        return $this->rowPrototype;
    }
}
