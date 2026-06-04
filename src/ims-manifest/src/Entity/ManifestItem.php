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

final class ManifestItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $manifestId,
        public readonly string $aoNumber,
        public readonly int $sku,
        public readonly string $vsn,
        public readonly string $specs,
        public readonly int $caseQty,
        public readonly bool $isDamaged,
        public readonly ?string $notes,
        public readonly int $scannedBy,
        public readonly DateTimeImmutable $scannedAt,
        // Populated when joined with sku_catalogue (detail view only)
        public readonly ?string $skuDescription = null,
        public readonly ?string $vendor = null,
        public readonly ?string $vendorModel = null,
    ) {}

    /**
     * Best available display name — falls back gracefully when sku_catalogue
     * has no row for this SKU yet.
     */
    public function displayName(): string
    {
        if ($this->skuDescription !== null && $this->skuDescription !== '') {
            return $this->skuDescription;
        }
        if ($this->vendorModel !== null && $this->vendorModel !== '') {
            return $this->vendorModel;
        }

        return 'SKU ' . $this->sku;
    }
}
