<?php

declare(strict_types=1);


namespace Webware\Acl\Listener;

use Webware\Acl\Event\RulesLoadedEvent;
use Webware\Acl\Exception\RuntimeException;

/**
 * Registers webware-acl module ACL rules.
 *
 * Grants Developer all privileges on admin.acl. Administrator is intentionally
 * excluded — granting Administrators ACL write access would allow them to lock
 * themselves and other Administrators out of the system.
 */
final readonly class RegisterAclRulesListener
{
    public function __construct() {}

    public const string SEED_ROLE_ID = 'Developer';
    public function __invoke(RulesLoadedEvent $event): void
    {
        if (! $event->acl->hasRole(self::SEED_ROLE_ID)) {
            throw new RuntimeException(sprintf(
                'Expected role "%s" to be registered before ACL rules are loaded.',
                self::SEED_ROLE_ID
            ));
        }
        $event->acl->allow(self::SEED_ROLE_ID);
    }
}
