<?php

declare(strict_types=1);


namespace Webware\Acl\Listener;

use Webware\Acl\Event\ResourcesLoadedEvent;

/**
 * Registers webware-acl module ACL resources.
 *
 * Invoked on ResourcesLoadedEvent; adds the admin.acl resource so that
 * ACL management UI routes can be protected and referenced by rules.
 */
final readonly class RegisterAclResourcesListener
{
    public function __construct(
        private string $resourceId,
    ) {
    }

    public function __invoke(ResourcesLoadedEvent $event): void
    {
        $event->acl->addResource($this->resourceId);
    }
}
