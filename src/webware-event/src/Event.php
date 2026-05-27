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

use Override;

class Event implements EventInterface, EventPropagationInterface
{
    use EventPropagationTrait;

    /**
     * @param array<array-key, mixed>|null $params
     */
    public function __construct(
        private ?string $name = null,
        private ?object $target = null,
        private array $params = [],
    ) {}

    #[Override]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    #[Override]
    public function getName(): string
    {
        return $this->name ?? static::class;
    }

    #[Override]
    public function setTarget(object $target): void
    {
        $this->target = $target;
    }

    #[Override]
    public function getTarget(): ?object
    {
        return $this->target;
    }

    #[Override]
    public function setParam(string $name, mixed $value): void
    {
        $this->params[$name] = $value;
    }

    #[Override]
    public function getParam(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    #[Override]
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    #[Override]
    public function getParams(): array
    {
        return $this->params;
    }
}
