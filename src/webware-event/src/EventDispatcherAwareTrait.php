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
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-ignore trait.unused
 */
trait EventDispatcherAwareTrait
{
    protected EventDispatcherInterface $eventDispatcher;

    #[Override]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    #[Override]
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }
}
