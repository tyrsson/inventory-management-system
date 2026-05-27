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

namespace Ims\Manifest\Entity;

use DateTimeImmutable;
use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;

use function array_filter;
use function array_values;
use function count;

final class Manifest implements ResourceInterface, ProprietaryInterface
{
    /**
     * @param ManifestItem[] $items      Full item list — populated by findById() only.
     * @param int            $itemCount  Line count from aggregate query (findAll).
     * @param int            $pieceCount Piece count from aggregate query (findAll).
     * @param int            $damagedCount Damaged line count from aggregate query.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $storeId,
        public readonly ?string $reference,
        public readonly DateTimeImmutable $receivedDate,
        public readonly int $createdBy,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?string $csvPath = null,
        public readonly array $items = [],
        public readonly int $itemCount = 0,
        public readonly int $pieceCount = 0,
        public readonly int $damagedCount = 0,
    ) {}

    /**
     * Human-readable identifier in store-MMDD format (e.g. "207-0415").
     * The DC reference is shown alongside in templates when present.
     */
    public function displayId(): string
    {
        return $this->storeId . '-' . $this->receivedDate->format('md');
    }

    /**
     * Total pieces received (SUM case_qty). Uses items[] when loaded, otherwise
     * falls back to the aggregate pieceCount from the list query.
     */
    public function totalPieces(): int
    {
        if ($this->items !== []) {
            $pieces = 0;
            foreach ($this->items as $item) {
                $pieces += $item->caseQty;
            }
            return $pieces;
        }
        return $this->pieceCount;
    }

    /**
     * Number of damaged lines. Uses items[] when loaded, otherwise falls back
     * to the aggregate damagedCount from the list query.
     */
    public function damagedLines(): int
    {
        if ($this->items !== []) {
            $count = 0;
            foreach ($this->items as $item) {
                if ($item->isDamaged) {
                    $count++;
                }
            }
            return $count;
        }
        return $this->damagedCount;
    }

    public function cleanLines(): int
    {
        return (count($this->items) ?: $this->itemCount) - $this->damagedLines();
    }

    /** @return ManifestItem[] */
    public function damagedItems(): array
    {
        return array_values(array_filter($this->items, static fn(ManifestItem $i): bool => $i->isDamaged));
    }

    /** @return ManifestItem[] */
    public function cleanItems(): array
    {
        return array_values(array_filter($this->items, static fn(ManifestItem $i): bool => ! $i->isDamaged));
    }

    public function getResourceId(): string
    {
        return 'manifest';
    }

    public function getOwnerId(): int
    {
        return $this->storeId;
    }
}
