<?php

declare(strict_types=1);

namespace Webware\Admin\Widget;

use FilterIterator;
use Iterator;
use Webware\Acl\Acl;
use Webware\Acl\AclInterface;
use Webware\UserManager\UserInterface;

/**
 * Wraps an iterator of WidgetInterface instances and accepts only those
 * the current user's roles are permitted to see, according to the ACL.
 *
 * @extends FilterIterator<int, WidgetInterface, Iterator<int, WidgetInterface>>
 */
final class AclWidgetFilterIterator extends FilterIterator
{
    /**
     * @param Iterator<int, WidgetInterface> $iterator
     */
    public function __construct(
        Iterator $iterator,
        private readonly AclInterface&Acl $acl,
        private readonly UserInterface|null $user,
    ) {
        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $widget = $this->current();

        if (! $widget instanceof WidgetInterface) {
            return false;
        }
        $check = $this->acl->isAllowed($this->user, $widget->resourceId, $widget->privilege);
        return $check;
    }
}
