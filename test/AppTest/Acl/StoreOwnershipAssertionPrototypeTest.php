<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Farmers Store Inventory package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppTest\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionAggregate;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Webware\Acl\Assertion\OwnershipAssertion;
use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\GenericResource;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\GenericRole;
use Laminas\Permissions\Acl\Role\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Prototype integration test — verifies that Laminas ACL + AssertionAggregate
 * can enforce both user-profile ownership and store-scoped ownership using only
 * Laminas primitives.
 *
 * Key design proven here:
 *   - User::getOwnerId() returns the user's PK (profile ownership)
 *   - Store scope is read via getDetail('store_id') in a separate assertion
 *   - Two distinct assertions are needed; AssertionAggregate can stack them
 *
 * All collaborators are anonymous classes — no dependency on application entities.
 */
#[CoversClass(OwnershipAssertion::class)]
#[CoversClass(AssertionAggregate::class)]
final class StoreOwnershipAssertionPrototypeTest extends TestCase
{
    private Acl $acl;

    // ── User: PK=1, storeId=42 ───────────────────────────────────────────────
    private RoleInterface&ProprietaryInterface $memberUser;

    // ── Another user: PK=2, storeId=99 ──────────────────────────────────────
    private RoleInterface&ProprietaryInterface $otherUser;

    // ── Warehouse user: storeId=42 (child of member) ─────────────────────────
    private RoleInterface&ProprietaryInterface $warehouseUser;

    // ── Warehouse Supervisor user: storeId=42 (grandchild of member) ─────────
    private RoleInterface&ProprietaryInterface $warehouseSupervisorUser;

    // ── Warehouse Supervisor user: storeId=99 (foreign store) ────────────────
    private RoleInterface&ProprietaryInterface $warehouseSupervisorForeignUser;

    // ── Administrator user: storeId=99 (foreign store, unrestricted) ─────────
    private RoleInterface&ProprietaryInterface $adminUser;

    // ── User profile resources ───────────────────────────────────────────────
    private ResourceInterface&ProprietaryInterface $ownProfile;
    private ResourceInterface&ProprietaryInterface $otherProfile;

    // ── Manifest resources ───────────────────────────────────────────────────
    private ResourceInterface&ProprietaryInterface $ownManifest;
    private ResourceInterface&ProprietaryInterface $foreignManifest;

    protected function setUp(): void
    {
        $this->acl = new Acl();

        // Role hierarchy for inheritance tests:
        // guest → member → Warehouse → Warehouse Supervisor → Administrator
        $this->acl->addRole(new GenericRole('member'));
        $this->acl->addRole(new GenericRole('Warehouse'), 'member');
        $this->acl->addRole(new GenericRole('Warehouse Supervisor'), 'Warehouse');
        $this->acl->addRole(new GenericRole('Administrator'), 'Warehouse Supervisor');

        $this->acl->addResource(new GenericResource('user.profile'));
        $this->acl->addResource(new GenericResource('store.manifest'));

        // ── Member user: userId=1, storeId=42 ────────────────────────────────
        // getOwnerId() returns userId (PK) — used for profile ownership.
        // getDetail('store_id') returns storeId — used for store-scope assertion.
        $this->memberUser = new class (1, 42) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(
                private readonly int $userId,
                private readonly int $storeId,
            ) {}

            public function getRoleId(): string { return 'member'; }

            public function getOwnerId(): int { return $this->userId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };

        // ── Other user: userId=2, storeId=99 ─────────────────────────────────
        $this->otherUser = new class (2, 99) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(
                private readonly int $userId,
                private readonly int $storeId,
            ) {}

            public function getRoleId(): string { return 'member'; }

            public function getOwnerId(): int { return $this->userId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };

        // ── Own profile: owned by userId=1 ───────────────────────────────────
        $this->ownProfile = new class (1) implements ResourceInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $ownerId) {}
            public function getResourceId(): string { return 'user.profile'; }
            public function getOwnerId(): int { return $this->ownerId; }
        };

        // ── Other user's profile: owned by userId=2 ──────────────────────────
        $this->otherProfile = new class (2) implements ResourceInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $ownerId) {}
            public function getResourceId(): string { return 'user.profile'; }
            public function getOwnerId(): int { return $this->ownerId; }
        };

        // ── Own manifest: owned by storeId=42 ────────────────────────────────
        $this->ownManifest = new class (42) implements ResourceInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getResourceId(): string { return 'store.manifest'; }
            public function getOwnerId(): int { return $this->storeId; }
        };

        // ── Foreign manifest: owned by storeId=99 ────────────────────────────
        $this->foreignManifest = new class (99) implements ResourceInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getResourceId(): string { return 'store.manifest'; }
            public function getOwnerId(): int { return $this->storeId; }
        };

        // ── Warehouse user: roleId='Warehouse', storeId=42 ───────────────────
        $this->warehouseUser = new class (42) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getRoleId(): string { return 'Warehouse'; }
            public function getOwnerId(): int { return $this->storeId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };

        // ── Warehouse Supervisor user: roleId='Warehouse Supervisor', storeId=42
        $this->warehouseSupervisorUser = new class (42) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getRoleId(): string { return 'Warehouse Supervisor'; }
            public function getOwnerId(): int { return $this->storeId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };

        // ── Warehouse Supervisor user: foreign store (storeId=99) ─────────────
        $this->warehouseSupervisorForeignUser = new class (99) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getRoleId(): string { return 'Warehouse Supervisor'; }
            public function getOwnerId(): int { return $this->storeId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };

        // ── Administrator user: roleId='Administrator', storeId=99 (foreign) ─
        // Expected to bypass assertion via explicit unrestricted allow.
        $this->adminUser = new class (99) implements RoleInterface, ProprietaryInterface
        {
            public function __construct(private readonly int $storeId) {}
            public function getRoleId(): string { return 'Administrator'; }
            public function getOwnerId(): int { return $this->storeId; }

            public function getDetail(string $name, mixed $default = null): mixed
            {
                return match ($name) {
                    'store_id' => $this->storeId,
                    default    => $default,
                };
            }
        };
    }

    /**
     * Profile ownership: Webware\Acl\Assertion\OwnershipAssertion — fail-closed.
     * Denies if either side lacks ProprietaryInterface or resource owner is null.
     */
    private function buildProfileOwnershipAssertion(): OwnershipAssertion
    {
        return new OwnershipAssertion();
    }

    /**
     * Store ownership: compares user's store_id (via getDetail) against resource's storeId (getOwnerId).
     * This will become StoreOwnershipAssertion in ims-store.
     */
    private function buildStoreOwnershipAssertion(): AssertionInterface
    {
        return new class implements AssertionInterface
        {
            public function assert(
                Acl $acl,
                ?RoleInterface $role = null,
                ?ResourceInterface $resource = null,
                $privilege = null,
            ): bool {
                if (! $resource instanceof ProprietaryInterface) {
                    return false;
                }

                if (! method_exists($role, 'getDetail')) {
                    return false;
                }

                return (int) $role->getDetail('store_id') === (int) $resource->getOwnerId();
            }
        };
    }

    private function buildAggregate(AssertionInterface $assertion): AssertionAggregate
    {
        $aggregate = new AssertionAggregate();
        $aggregate->addAssertion($assertion);

        return $aggregate;
    }

    // ── Profile ownership tests ───────────────────────────────────────────────

    #[Test]
    public function memberCanEditOwnProfile(): void
    {
        $this->acl->allow('member', 'user.profile', 'edit', $this->buildProfileOwnershipAssertion());

        self::assertTrue(
            $this->acl->isAllowed($this->memberUser, $this->ownProfile, 'edit'),
        );
    }

    #[Test]
    public function memberCannotEditOtherUsersProfile(): void
    {
        $this->acl->allow('member', 'user.profile', 'edit', $this->buildProfileOwnershipAssertion());

        self::assertFalse(
            $this->acl->isAllowed($this->memberUser, $this->otherProfile, 'edit'),
        );
    }

    #[Test]
    public function aggregateEnforcesProfileOwnership(): void
    {
        $this->acl->allow('member', 'user.profile', 'edit', $this->buildAggregate($this->buildProfileOwnershipAssertion()));

        self::assertTrue($this->acl->isAllowed($this->memberUser, $this->ownProfile, 'edit'));
        self::assertFalse($this->acl->isAllowed($this->memberUser, $this->otherProfile, 'edit'));
    }

    // ── Store ownership tests ─────────────────────────────────────────────────

    #[Test]
    public function memberCanSaveOwnStoreManifest(): void
    {
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        self::assertTrue(
            $this->acl->isAllowed($this->memberUser, $this->ownManifest, 'save'),
        );
    }

    #[Test]
    public function memberCannotSaveForeignStoreManifest(): void
    {
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        self::assertFalse(
            $this->acl->isAllowed($this->memberUser, $this->foreignManifest, 'save'),
        );
    }

    #[Test]
    public function aggregateEnforcesStoreOwnership(): void
    {
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildAggregate($this->buildStoreOwnershipAssertion()));

        self::assertTrue($this->acl->isAllowed($this->memberUser, $this->ownManifest, 'save'));
        self::assertFalse($this->acl->isAllowed($this->memberUser, $this->foreignManifest, 'save'));
    }

    // ── Assertion inheritance tests ───────────────────────────────────────────
    //
    // These tests answer the open question: when a parent role (member) has an
    // allow rule with an assertion attached, does the assertion also fire when
    // isAllowed() is called with a child/grandchild role that has no explicit rule?
    //
    // The Laminas ACL docs do not state this explicitly. These tests are the
    // authoritative record of the actual runtime behaviour.
    //
    // Setup: 'member' has allow on 'store.manifest'/'save' with StoreOwnedResourceAssertion.
    //        'Warehouse' inherits from 'member' — no explicit rule.
    //        'Warehouse Supervisor' inherits from 'Warehouse' — no explicit rule.
    //        'Administrator' has a separate explicit allow with NO assertion.

    #[Test]
    public function childRoleInheritsAssertionFromParent(): void
    {
        // Only member has an explicit rule — Warehouse inherits it.
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        // Warehouse user, same store as manifest owner — should be allowed.
        // This passes ONLY if the inherited rule's assertion also fires correctly.
        self::assertTrue(
            $this->acl->isAllowed($this->warehouseUser, $this->ownManifest, 'save'),
            'Warehouse (child of member) must be allowed for own-store manifest via inherited assertion',
        );
    }

    #[Test]
    public function childRoleAssertionDeniesForForeignStore(): void
    {
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        // Warehouse user from store 42, manifest owned by store 99 — should be denied.
        self::assertFalse(
            $this->acl->isAllowed($this->warehouseUser, $this->foreignManifest, 'save'),
            'Warehouse (child of member) must be denied for foreign-store manifest via inherited assertion',
        );
    }

    #[Test]
    public function grandchildRoleInheritsAssertionFromAncestor(): void
    {
        // Warehouse Supervisor is two levels below member — no explicit rule on either
        // Warehouse or Warehouse Supervisor.
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        // Warehouse Supervisor, same store — should be allowed.
        self::assertTrue(
            $this->acl->isAllowed($this->warehouseSupervisorUser, $this->ownManifest, 'save'),
            'Warehouse Supervisor (grandchild of member) must be allowed for own-store manifest via inherited assertion',
        );
    }

    #[Test]
    public function grandchildRoleAssertionDeniesForForeignStore(): void
    {
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());

        // Warehouse Supervisor from store 99 against manifest owned by store 42 — should be denied.
        self::assertFalse(
            $this->acl->isAllowed($this->warehouseSupervisorForeignUser, $this->ownManifest, 'save'),
            'Warehouse Supervisor (grandchild of member) must be denied for foreign-store manifest via inherited assertion',
        );
    }

    #[Test]
    public function explicitUnrestrictedAllowOverridesInheritedAssertionRule(): void
    {
        // member has assertion-guarded rule; Administrator has an explicit allow with no assertion.
        // The explicit rule on Administrator should take precedence over the inherited member rule.
        $this->acl->allow('member', 'store.manifest', 'save', $this->buildStoreOwnershipAssertion());
        $this->acl->allow('Administrator', 'store.manifest', 'save');

        // Admin user is in store 99 but the manifest belongs to store 42 — should still be allowed
        // because the Administrator's own explicit rule has no assertion.
        self::assertTrue(
            $this->acl->isAllowed($this->adminUser, $this->ownManifest, 'save'),
            'Administrator with explicit unrestricted allow must bypass inherited assertion',
        );
    }
}
