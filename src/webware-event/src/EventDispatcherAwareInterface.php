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

use Psr\EventDispatcher\EventDispatcherInterface;

interface EventDispatcherAwareInterface
{
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void;

    public function getEventDispatcher(): EventDispatcherInterface;
}
