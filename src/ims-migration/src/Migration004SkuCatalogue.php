<?php

declare(strict_types=1);

namespace Ims\Migration;

use Ims\Migration\Column\MediumInteger;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Argument\Literal as ArgLiteral;
use PhpDb\Sql\Ddl\Column\Datetime;
use PhpDb\Sql\Ddl\Column\Json;
use PhpDb\Sql\Ddl\Column\SmallInteger;
use PhpDb\Sql\Ddl\Column\Varchar;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Literal;
use PhpDb\Sql\Sql;

final class Migration004SkuCatalogue implements MigrationInterface
{
    public function getStep(): int
    {
        return 4;
    }

    public function getDescription(): string
    {
        return 'Create sku_catalogue table';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql    = new Sql($adapter);
        $create = new CreateTable('sku_catalogue');
        $create->ifNotExists();

        $create->addColumn(
            (new MediumInteger('sku', nullable: false))
                ->setOptions([
                    'unsigned' => true,
                    'comment'  => '6-digit Farmers SKU',
                ])
        );

        $create->addColumn(new Varchar('description', 255, nullable: false, default: ''));

        $create->addColumn(
            (new Varchar('vendor', 50, nullable: false, default: ''))
                ->setOptions(['comment' => 'DC vendor abbreviation (e.g. EMBY)'])
        );

        $create->addColumn(new Varchar('vendor_model', 50, nullable: false, default: ''));

        $create->addColumn(
            (new SmallInteger('major_code_id', nullable: true))
                ->setOptions(['unsigned' => true])
        );

        // ON UPDATE CURRENT_TIMESTAMP is not yet supported by the DDL builder;
        // the column is created with DEFAULT CURRENT_TIMESTAMP only.
        // @todo: raise upstream feature request for ON UPDATE support.
        $create->addColumn(
            new DateTime('updated_at', nullable: false, default: new ArgLiteral('CURRENT_TIMESTAMP'))
        );

        $create->addColumn(
            (new Json('params', nullable: true))
                ->setOptions(['comment' => 'Plugin extension data'])
        );

        $create->addConstraint(new PrimaryKey('sku'));
        $create->addConstraint(new ForeignKey('fk_sku_major_code', 'major_code_id', 'major_code', 'id'));

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
            $sql->buildSqlString((new DropTable('sku_catalogue'))->ifExists()),
            AdapterInterface::QUERY_MODE_EXECUTE
        );
    }
}
