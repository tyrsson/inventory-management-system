<?php

declare(strict_types=1);

namespace Ims\Migration;

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

final class Migration011TicketItem implements MigrationInterface
{
    public function getStep(): int
    {
        return 11;
    }

    public function getDescription(): string
    {
        return 'Create ticket_item table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('ticket_item');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('ticket_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Integer('product_id', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Integer('confirmed_by', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new Datetime('confirmed_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new UniqueKey(['ticket_id', 'product_id'], 'uq_ticket_item'));
        $create->addConstraint(new ForeignKey('fk_ti_ticket', 'ticket_id', 'ticket', 'id'));
        $create->addConstraint(new ForeignKey('fk_ti_product', 'product_id', 'product', 'id'));
        $create->addConstraint(new ForeignKey('fk_ti_confirmed_by', 'confirmed_by', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('ticket_item'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
