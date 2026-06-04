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

namespace Htmx;

use BackedEnum;
use ValueError;

/**
 * Utility methods for backed enums.
 *
 * Provides fromName(), names(), values(), and toArray() — the natural
 * complements to the built-in from() / tryFrom() methods on backed enums.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait EnumTrait
{
    /** @return list<BackedEnum> */
    abstract public static function cases(): array;

    /** Return the case whose name matches $name, or null if not found. */
    public static function tryFromName(string $name): ?static
    {
        foreach (static::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    /** Return the case whose name matches $name, or throw \ValueError if not found. */
    public static function fromName(string $name): static
    {
        return static::tryFromName($name) ?? throw new ValueError('"' . $name . '" is not a valid name for enum "' . static::class . '"');
    }

    /**
     * Return an array of all case names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(static::cases(), 'name');
    }

    /**
     * Return an array of all case values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    /**
     * Return an associative array of case values to names, or names to treated values.
     *
     * @param bool $normalize When true, keys are names and values are (optionally treated) values.
     * @param callable|string|null $valueTreatment A callable or function name applied to each value when $normalize is true.
     * @return array<string, string>
     */
    public static function toArray(bool $normalize = false, string|callable|null $valueTreatment = null): array
    {
        if ($normalize) {
            $values = $valueTreatment !== null
                ? array_map($valueTreatment, array_column(static::cases(), 'value'))
                : array_column(static::cases(), 'value');

            return array_combine(array_column(static::cases(), 'name'), $values);
        }

        return array_column(static::cases(), 'name', 'value');
    }
}
