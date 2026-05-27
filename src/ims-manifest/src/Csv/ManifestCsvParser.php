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
use RuntimeException;

use function array_combine;
use function count;
use function fclose;
use function fgetcsv;
use function fopen;
use function max;
use function preg_match;
use function sprintf;
use function substr;
use function trim;

/**
 * Parses a DC truck manifest CSV file into a ParsedManifest value object.
 *
 * Expected file structure:
 *   Row 0: textbox39,textbox40                              (skip — form artifact)
 *   Row 1: "Consignment:  207-0427","Sort Order:  Sku ID"  (store_id + reference)
 *   blank line                                              (skip)
 *   Row 2: column headers                                   (map by name)
 *   Row 3+: data rows                                       (skip rows with empty TagID)
 *
 * Customer allocation rows (TagID empty, CustName filled) are footnotes that
 * indicate a DC-to-customer allocation — they are not physical floor stock
 * and are silently skipped.
 */
final class ManifestCsvParser
{
    /**
     * Parse a DC truck manifest CSV file.
     *
     * @param string                  $filePath             Absolute path to the uploaded CSV.
     * @param DateTimeImmutable|null  $receivedDateOverride When provided, overrides the date
     *                                                      derived from the consignment string.
     *
     * @throws RuntimeException when the file cannot be read or the consignment header is missing.
     */
    public function parse(string $filePath, ?DateTimeImmutable $receivedDateOverride = null): ParsedManifest
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot open manifest CSV: %s', $filePath));
        }

        try {
            return $this->doParse($handle, $receivedDateOverride);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function doParse(mixed $handle, ?DateTimeImmutable $receivedDateOverride): ParsedManifest
    {
        $rowIndex     = -1;
        $storeId      = 0;
        $reference    = '';
        $receivedDate = null;
        $headers      = null;
        $items        = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            // fgetcsv returns [null] for blank / empty lines — skip without counting
            if ($row === [null]) {
                continue;
            }

            $rowIndex++;

            if ($rowIndex === 0) {
                // textbox39,textbox40 — form field name artifacts, ignore
                continue;
            }

            if ($rowIndex === 1) {
                // "Consignment:  207-0427, Sort Order:  Sku ID"
                $this->parseConsignmentRow($row, $storeId, $reference, $receivedDate);
                continue;
            }

            // First non-blank non-consignment row is the header row
            if ($headers === null) {
                $headers = $row;
                continue;
            }

            // Data row — skip malformed lines that do not match the header count
            if (count($row) !== count($headers)) {
                continue;
            }

            /** @var array<string, string> $data */
            $data = array_combine($headers, $row);
            $this->parseDataRow($data, $items);
        }

        if ($storeId === 0 || $reference === '') {
            throw new RuntimeException(
                'Could not parse consignment header from CSV. '
                . 'Expected a row matching "Consignment:  {store}-{MMDD}".'
            );
        }

        $date = $receivedDateOverride ?? $receivedDate ?? new DateTimeImmutable();

        return new ParsedManifest($storeId, $reference, $date, $items);
    }

    /**
     * Extract store ID, reference string, and received date from the consignment row.
     *
     * Format: "Consignment:  207-0427"  →  store=207, ref="207-0427", date=current-year/04/27
     *
     * @param string[]                $row
     */
    private function parseConsignmentRow(
        array $row,
        int &$storeId,
        string &$reference,
        ?DateTimeImmutable &$receivedDate,
    ): void {
        $cell = trim($row[0] ?? '');

        if (! preg_match('/Consignment:\s*(\d+)-(\d{4})/i', $cell, $m)) {
            return;
        }

        $storeId   = (int) $m[1];
        $reference = $m[1] . '-' . $m[2];

        $mmdd  = $m[2];                                         // e.g. "0427"
        $month = (int) substr($mmdd, 0, 2);
        $day   = (int) substr($mmdd, 2, 2);
        $year  = (int) (new DateTimeImmutable())->format('Y');

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            sprintf('%d-%02d-%02d', $year, $month, $day)
        );

        $receivedDate = $parsed !== false ? $parsed : null;
    }

    /**
     * Parse one data row into a ParsedManifestItem and append to $items.
     * Rows where TagID is empty are customer allocation footnotes — skip them.
     *
     * @param array<string, string> $data
     * @param ParsedManifestItem[]  $items
     */
    private function parseDataRow(array $data, array &$items): void
    {
        $aoNumber = trim($data['TagID'] ?? '');

        // Customer allocation rows have no TagID — they are not physical floor stock
        if ($aoNumber === '') {
            return;
        }

        $sku     = (int) trim($data['SkuID']        ?? '0');
        $vsn     = trim($data['vsn1']               ?? '');
        $specs   = trim($data['SkuDescription']     ?? '');
        $caseQty = max(1, (int) trim($data['Qty']   ?? '1'));
        $majCode = trim($data['MajorCode']           ?? '');
        $vendor  = trim($data['VendorName']          ?? '');

        if ($sku === 0) {
            return; // malformed row without a valid SKU
        }

        $items[] = new ParsedManifestItem(
            aoNumber:   $aoNumber,
            sku:        $sku,
            vsn:        $vsn,
            specs:      $specs,
            caseQty:    $caseQty,
            majorCode:  $majCode,
            vendorName: $vendor,
        );
    }
}
