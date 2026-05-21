<?php

declare(strict_types=1);

namespace Webware\UserManager\Listener;

use Webware\Acl\Event\ResourcesLoadedEvent;

final readonly class RegisterUserManagerResourcesListener
{
    public function __construct(
        private string $routeNamePrefix,
    ) {
    }

    public function __invoke(ResourcesLoadedEvent $event): void
    {
        $event->acl->addResource($this->routeNamePrefix . 'session.read');
        $event->acl->addResource($this->routeNamePrefix . 'session.create');
        $event->acl->addResource($this->routeNamePrefix . 'logout.read');
        $event->acl->addResource($this->routeNamePrefix . 'account.read');
        $event->acl->addResource($this->routeNamePrefix . 'account.create');
        $event->acl->addResource($this->routeNamePrefix . 'verify.email.read');
        $event->acl->addResource($this->routeNamePrefix . 'resend.verification.read');
        $event->acl->addResource($this->routeNamePrefix . 'resend.verification.create');
    }
}
