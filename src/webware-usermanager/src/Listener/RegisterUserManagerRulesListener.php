<?php

declare(strict_types=1);

namespace Webware\UserManager\Listener;

use Webware\Acl\Event\RulesLoadedEvent;
use Webware\Acl\Exception\RuntimeException;

final readonly class RegisterUserManagerRulesListener
{
    public const string GUEST_ROLE = 'Guest';

    public function __construct(
        private string $routeNamePrefix,
    ) {
    }

    public function __invoke(RulesLoadedEvent $event): void
    {
        if (! $event->acl->hasRole(self::GUEST_ROLE)) {
            throw new RuntimeException(sprintf(
                'Expected role "%s" to be registered before ACL rules are loaded.',
                self::GUEST_ROLE
            ));
        }

        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'session.read');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'session.create');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'account.read');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'account.create');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'verify.email.read');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'resend.verification.read');
        $event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'resend.verification.create');
    }
}
