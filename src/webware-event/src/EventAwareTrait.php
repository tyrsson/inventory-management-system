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

trait EventAwareTrait
{
    private ?EventInterface $event = null;

    #[Override]
    public function getEvent(): ?EventInterface
    {
        return $this->event;
    }

    #[Override]
    public function setEvent(EventInterface $event): void
    {
        $this->event = $event;
    }
}
