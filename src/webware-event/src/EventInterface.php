<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Event package.
 *
 * Copyright (c) 2026 Joey (aka Tyrsson) Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Event;

interface EventInterface
{
    public function setName(string $name): void;

    public function getName(): string;

    public function getTarget(): ?object;

    public function setTarget(object $target): void;

    public function setParam(string $name, mixed $value): void;

    public function getParam(string $name, mixed $default = null): mixed;

    public function setParams(array $params): void;
    
    /**
     * @return array<array-key, mixed>
     */
    public function getParams(): array;

}
