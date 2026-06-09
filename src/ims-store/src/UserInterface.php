<?php

declare(strict_types=1);

namespace Ims\Store;

use Ims\Store\Acl\StoreProprietaryInterface;
use Webware\UserManager\UserInterface as WebwareUserInterface;

/**
 * Extends the base UserInterface with the store-proprietary contract so that
 * a user can express which store they belong to. This keeps webware-usermanager
 * free of any ims-store dependency while allowing the StoreOwnedResourceAssertion
 * to compare store ownership on both the role and resource sides.
 */
interface UserInterface extends StoreProprietaryInterface, WebwareUserInterface {}
