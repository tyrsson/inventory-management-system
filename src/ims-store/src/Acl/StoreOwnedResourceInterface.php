<?php

declare(strict_types=1);


namespace Ims\Store\Acl;

use Laminas\Permissions\Acl\Resource\ResourceInterface;

/**
 * Convenience composite for any object that is simultaneously a Laminas ACL
 * resource AND belongs to a specific store.
 *
 * Implement this on commands and entities that must pass store-scope assertions
 * (e.g. StoreOwnedResourceAssertion). It combines ResourceInterface (so Laminas
 * ACL can use it as a resource) with StoreProprietaryInterface (so the assertion
 * can compare store IDs on both sides).
 *
 * Current implementors: SaveManifestCommand (ims-manifest)
 *
 * Do NOT remove — actively used by ims-manifest and any future store-scoped
 * command/entity that participates in ACL checks.
 */
interface StoreOwnedResourceInterface extends
    ResourceInterface,
    StoreProprietaryInterface
{}
