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

use DateTimeImmutable;

/**
 * Parsed representation of a DC truck manifest CSV file.
 * Immutable value object passed from the parser to the repository.
 */
final class ParsedManifest
{
    /** @param ParsedManifestItem[] $items */
    public function __construct(
        public readonly int $storeId,
        public readonly string $reference,
        public readonly DateTimeImmutable $receivedDate,
        public readonly array $items,
    ) {}
}
