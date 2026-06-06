<?php

declare(strict_types=1);

namespace Ims\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration009ProductImage implements MigrationInterface
{
    public function getStep(): int
    {
        return 9;
    }

    public function getDescription(): string
    {
        return 'Create product_image table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('product_image');
        $create->ifNotExists();

        $create->addColumn(
            (new Integer('id', nullable: false))
                ->setOptions(['unsigned' => true, 'autoincrement' => true])
        );

        $create->addColumn(
            (new Integer('productId', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            (new Varchar('filename', 255))
                ->setOptions(['comment' => 'Path relative to image storage root'])
        );

        $create->addColumn(
            (new Integer('uploadedBy', nullable: false))
                ->setOptions(['unsigned' => true])
        );

        $create->addColumn(
            new Datetime('uploaded_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addConstraint(new PrimaryKey('id'));
        $create->addConstraint(new Index('productId', 'idx_pi_product'));
        $create->addConstraint(new ForeignKey('fk_pi_product', 'productId', 'product', 'id'));
        $create->addConstraint(new ForeignKey('fk_pi_uploaded_by', 'uploadedBy', 'user', 'id'));

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
            $sql->buildSqlString((new DropTable('product_image'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
