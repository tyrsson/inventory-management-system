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

namespace Ims\Manifest\Csv;

/**
 * Represents a single item row parsed from a DC truck manifest CSV.
 * Immutable value object — no ID, no manifest FK (those are assigned on insert).
 */
final class ParsedManifestItem
{
    public function __construct(
        public readonly string $aoNumber,
        public readonly int $sku,
        public readonly string $vsn,
        public readonly string $specs,
        public readonly int $caseQty,
        public readonly string $majorCode,
        public readonly string $vendorName,
    ) {}
}
