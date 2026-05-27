<?php

declare(strict_types=1);

namespace Ims\Migration\Column;

use PhpDb\Sql\Argument\Literal;
use PhpDb\Sql\Ddl\Column\Column;

use function implode;
use function sprintf;

/**
 * ENUM column — not provided by phpdb core.
 *
 * Usage:
 *   new Enum('status', ['Pending', 'Completed', 'Cancelled'])
 *   new Enum('type',   ['allow', 'deny'], nullable: false, default: 'allow')
 */
final class Enum extends Column
{
    /** @var string[] */
    private array $values;

    /**
     * @param string[] $values
     */
    public function __construct(
        string $name,
        array $values,
        bool $nullable = false,
        string|int|float|bool|Literal|null $default = null,
        array $options = []
    ) {
        $this->values = $values;

        parent::__construct($name, $nullable, $default, $options);
    }

    /** @inheritDoc */
    public function getExpressionData(): array
    {
        $quoted = [];
        foreach ($this->values as $v) {
            $quoted[] = "'" . $v . "'";
        }

        $enumType = sprintf('ENUM(%s)', implode(',', $quoted));

        $data = parent::getExpressionData();

        // Parent spec: '%s %s NOT NULL ...' — first %s = name, second %s = type.
        // Replace '%s %s' with '%s ENUM(...)' and remove the type Literal from values.
        $data['spec'] = str_replace('%s %s', '%s ' . $enumType, $data['spec']);
        array_splice($data['values'], 1, 1);

        return $data;
    }
}
