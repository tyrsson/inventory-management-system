<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ims\Manifest\Repository;

use DateTimeImmutable;
use Ims\Manifest\Csv\ParsedManifest;
use Ims\Manifest\Entity\Manifest;
use Ims\Manifest\Entity\ManifestItem;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Expression;
use PhpDb\Sql\Sql;

use function sprintf;

final class ManifestRepository implements ManifestRepositoryInterface
{
    public function __construct(
        private readonly AdapterInterface $adapter,
    ) {}

    #[Override]
    public function findAll(int $limit = 25, int $offset = 0): array
    {
        $sql    = new Sql($this->adapter, 'manifest');
        $select = $sql->select()
            ->columns(['id', 'store_id', 'reference', 'received_date', 'created_by', 'created_at'])
            ->join(
                ['mi' => 'manifest_item'],
                'mi.manifest_id = manifest.id',
                [
                    'item_count'    => new Expression('COUNT(mi.id)'),
                    'piece_count'   => new Expression('COALESCE(SUM(mi.case_qty), 0)'),
                    'damaged_count' => new Expression('COALESCE(SUM(mi.is_damaged), 0)'),
                ],
                'left outer'
            )
            ->group('manifest.id')
            ->order(['manifest.received_date DESC', 'manifest.id DESC'])
            ->limit($limit)
            ->offset($offset);

        $result = $sql->prepareStatementForSqlObject($select)->execute();

        $manifests = [];
        foreach ($result as $row) {
            $manifests[] = $this->hydrateManifest($row);
        }
        return $manifests;
    }

    #[Override]
    public function countAll(): int
    {
        $sql    = new Sql($this->adapter, 'manifest');
        $select = $sql->select()->columns(['total' => new Expression('COUNT(*)')]);
        $row    = $sql->prepareStatementForSqlObject($select)->execute()->current();
        return (int) ($row['total'] ?? 0);
    }

    #[Override]
    public function findById(int $id): ?Manifest
    {
        $sql    = new Sql($this->adapter, 'manifest');
        $select = $sql->select()
            ->columns(['id', 'store_id', 'reference', 'received_date', 'created_by', 'created_at'])
            ->where(['manifest.id' => $id])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();
        if ($row === null) {
            return null;
        }

        $items = $this->fetchItemsForManifest($id);

        return $this->hydrateManifest($row, $items);
    }

    #[Override]
    public function insert(array $data): int
    {
        $sql    = new Sql($this->adapter, 'manifest');
        $insert = $sql->insert()->values($data);
        $sql->prepareStatementForSqlObject($insert)->execute();
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    #[Override]
    public function insertFromCsv(ParsedManifest $parsed, int $userId, string $csvPath): int
    {
        // 1. Resolve / create major_code rows; cache by code string to avoid repeat queries
        $majorCodeIds = [];
        foreach ($parsed->items as $item) {
            $code = $item->majorCode;
            if ($code !== '' && ! isset($majorCodeIds[$code])) {
                $majorCodeIds[$code] = $this->resolveMajorCodeId($code);
            }
        }

        // 2. Upsert sku_catalogue rows for each unique SKU
        $processedSkus = [];
        foreach ($parsed->items as $item) {
            if (! isset($processedSkus[$item->sku])) {
                $majorCodeId = $majorCodeIds[$item->majorCode] ?? null;
                $this->upsertSkuCatalogue($item->sku, $item->specs, $item->vendorName, $item->vsn, $majorCodeId);
                $processedSkus[$item->sku] = true;
            }
        }

        // 3. Insert the manifest header row
        $now        = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $manifestId = $this->insert([
            'store_id'      => $parsed->storeId,
            'reference'     => $parsed->reference,
            'received_date' => $parsed->receivedDate->format('Y-m-d'),
            'created_by'    => $userId,
            'created_at'    => $now,
            'csv_path'      => $csvPath,
        ]);

        // 4. Insert manifest_item rows; scanned_by = uploading user (import = initial record)
        $sql = new Sql($this->adapter, 'manifest_item');
        foreach ($parsed->items as $item) {
            $insert = $sql->insert()->values([
                'manifest_id' => $manifestId,
                'ao_number'   => $item->aoNumber,
                'sku'         => $item->sku,
                'vsn'         => $item->vsn,
                'specs'       => $item->specs,
                'case_qty'    => $item->caseQty,
                'is_damaged'  => 0,
                'notes'       => null,
                'scanned_by'  => $userId,
                'scanned_at'  => $now,
            ]);
            $sql->prepareStatementForSqlObject($insert)->execute();
        }

        return $manifestId;
    }

    /**
     * Return the primary key of the given major_code, creating the row if it does not exist.
     * Unknown codes are inserted with the code string as the description placeholder.
     */
    private function resolveMajorCodeId(string $code): int
    {
        $sql    = new Sql($this->adapter, 'major_code');
        $select = $sql->select()->columns(['id'])->where(['code' => $code])->limit(1);
        $row    = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if ($row !== null && $row !== false) {
            return (int) $row['id'];
        }

        $insert = $sql->insert()->values(['code' => $code, 'description' => $code]);
        $sql->prepareStatementForSqlObject($insert)->execute();
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Insert or update a sku_catalogue row with CSV-supplied data.
     * Always overwrites with the latest CSV values — the DC system is authoritative.
     */
    private function upsertSkuCatalogue(
        int $sku,
        string $description,
        string $vendor,
        string $vendorModel,
        ?int $majorCodeId,
    ): void {
        $sql    = new Sql($this->adapter, 'sku_catalogue');
        $select = $sql->select()->columns(['sku'])->where(['sku' => $sku])->limit(1);
        $row    = $sql->prepareStatementForSqlObject($select)->execute()->current();

        $data = [
            'description'  => $description,
            'vendor'       => $vendor,
            'vendor_model' => $vendorModel,
            'major_code_id' => $majorCodeId,
        ];

        if ($row === null || $row === false) {
            $insert = $sql->insert()->values(['sku' => $sku] + $data);
            $sql->prepareStatementForSqlObject($insert)->execute();
        } else {
            $update = $sql->update()->set($data)->where(['sku' => $sku]);
            $sql->prepareStatementForSqlObject($update)->execute();
        }
    }

    /** @return ManifestItem[] */
    private function fetchItemsForManifest(int $manifestId): array
    {
        $sql    = new Sql($this->adapter, 'manifest_item');
        $select = $sql->select()
            ->columns(['id', 'manifest_id', 'ao_number', 'sku', 'vsn', 'specs', 'case_qty', 'is_damaged', 'notes', 'scanned_by', 'scanned_at'])
            ->join(
                ['sc' => 'sku_catalogue'],
                'sc.sku = manifest_item.sku',
                ['sku_description' => 'description', 'vendor', 'vendor_model'],
                'left outer'
            )
            ->where(['manifest_item.manifest_id' => $manifestId])
            ->order('manifest_item.scanned_at ASC');

        $result = $sql->prepareStatementForSqlObject($select)->execute();

        $items = [];
        foreach ($result as $row) {
            $items[] = $this->hydrateManifestItem($row);
        }
        return $items;
    }

    /** @param array<string, mixed> $row */
    private function hydrateManifest(array $row, array $items = []): Manifest
    {
        return new Manifest(
            id:           (int) $row['id'],
            storeId:      (int) $row['store_id'],
            reference:    ($row['reference'] !== null && $row['reference'] !== '') ? (string) $row['reference'] : null,
            receivedDate: new DateTimeImmutable((string) $row['received_date']),
            createdBy:    (int) $row['created_by'],
            createdAt:    new DateTimeImmutable((string) $row['created_at']),
            csvPath:      ($row['csv_path'] ?? null) !== null ? (string) $row['csv_path'] : null,
            items:        $items,
            itemCount:    (int) ($row['item_count'] ?? 0),
            pieceCount:   (int) ($row['piece_count'] ?? 0),
            damagedCount: (int) ($row['damaged_count'] ?? 0),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateManifestItem(array $row): ManifestItem
    {
        return new ManifestItem(
            id:             (int) $row['id'],
            manifestId:     (int) $row['manifest_id'],
            aoNumber:       (string) $row['ao_number'],
            sku:            (int) $row['sku'],
            vsn:            (string) $row['vsn'],
            specs:          (string) $row['specs'],
            caseQty:        (int) $row['case_qty'],
            isDamaged:      (bool) $row['is_damaged'],
            notes:          ($row['notes'] ?? null) !== null ? (string) $row['notes'] : null,
            scannedBy:      (int) $row['scanned_by'],
            scannedAt:      new DateTimeImmutable((string) $row['scanned_at']),
            skuDescription: ($row['sku_description'] ?? null) !== null ? (string) $row['sku_description'] : null,
            vendor:         ($row['vendor'] ?? null) !== null ? (string) $row['vendor'] : null,
            vendorModel:    ($row['vendor_model'] ?? null) !== null ? (string) $row['vendor_model'] : null,
        );
    }
}
