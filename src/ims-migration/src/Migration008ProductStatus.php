<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\Enum;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration008ProductStatus implements MigrationInterface
{
    public function getStep(): int
    {
        return 8;
    }

    public function getDescription(): string
    {
        return 'Create product_status table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('product_status');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('product_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new Enum('status', ['Overstock', 'Damaged', 'Floor', 'Pending PQA', 'Bargain Center', 'Reparable', 'Non Reparable'])
        );

        $create->addColumn(
            (new Integer('set_by', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new DateTime('set_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey(['product_id', 'status'], 'uq_product_status_flag'));
        $create->addConstraint(new ForeignKey('fk_ps_product', 'product_id', 'product', 'id'));
        $create->addConstraint(new ForeignKey('fk_ps_set_by', 'set_by', 'user', 'id'));

        $create->setOptions([
            'engine'          => new Literal('InnoDB'),
            'default charset' => new Literal('utf8mb4'),
            'collate'         => new Literal('utf8mb4_unicode_ci'),
        ]);

        $adapter->query(
            $sql->buildSqlString($create),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }

    public function down(AdapterInterface $adapter): void
    {
        $sql = new Sql($adapter);

        $adapter->query(
            $sql->buildSqlString((new DropTable('product_status'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
