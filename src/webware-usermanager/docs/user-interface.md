# UserInterface Contract

`Webware\UserManager\UserInterface` is the **canonical user identity type** for
the webware package ecosystem. It extends `Mezzio\Authentication\UserInterface`
with the three Laminas ACL interfaces required for ownership-based access
control:

```php
namespace Webware\UserManager;

use Laminas\Permissions\Acl\ProprietaryInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Mezzio\Authentication\UserInterface as MezzioUserInterface;

interface UserInterface extends
    MezzioUserInterface,
    RoleInterface,
    ResourceInterface,
    ProprietaryInterface
{
    public function isGuest(): bool;
}
```

---

## Why this interface exists

`Mezzio\Authentication\UserInterface` only covers identity (`getIdentity()`,
`getRoles()`, `getDetail()`). It carries no information that Laminas ACL can
use for role-based or ownership-based checks.

`Webware\UserManager\UserInterface` adds:

| Interface | Provided by | Required for |
|---|---|---|
| `RoleInterface` | `laminas/laminas-permissions-acl` | `$acl->isAllowed($user, ...)` |
| `ResourceInterface` | `laminas/laminas-permissions-acl` | User-profile ownership assertion |
| `ProprietaryInterface` | `laminas/laminas-permissions-acl` | `getOwnerId()` — used by `OwnershipAssertion` |

`isGuest()` is an additional method not derived from any parent interface. It
allows any consumer to distinguish a guest (anonymous) user from an
authenticated user without inspecting role strings or checking `null`.

Every concrete `User` entity produced by the authentication layer **must**
implement this interface so that ACL checks and ownership assertions work
without additional type casting.

---

## Required host-application DI alias

`webware-acl` resolves `Mezzio\Authentication\UserInterface::class` from the
container — that is the interface the Mezzio authentication session adapter
uses to restore the user between requests.

The host application **must** alias Mezzio's interface to this one in its
container configuration so that every resolution of the Mezzio interface
yields a concrete class that also satisfies the richer contract:

```php
// config/autoload/dependencies.global.php  (host application)

use Mezzio\Authentication\UserInterface as MezzioUserInterface;
use Webware\UserManager\UserInterface as UserManagerUserInterface;

return [
    'dependencies' => [
        'aliases' => [
            // Anything resolving Mezzio's UserInterface gets our richer
            // implementation, which satisfies all ACL interfaces.
            MezzioUserInterface::class => UserManagerUserInterface::class,
        ],
        'factories' => [
            // The concrete factory that creates User instances must be
            // registered under our interface key.
            UserManagerUserInterface::class => \Webware\UserManager\Container\UserFactory::class,
        ],
    ],
];
```

> **Why in the host app, not in a package?**  
> `webware-acl` must not depend on `webware-usermanager` (circular) and
> `webware-usermanager` must not own the DI key for `Mezzio\Authentication\UserInterface`
> (it does not own the Mezzio authentication package). The alias is a host-app
> wiring concern — it is the only place where all three packages are in scope.

---

## Concrete class requirements

Any class used as the concrete implementation must:

1. Implement `Webware\UserManager\UserInterface` (satisfies all four interfaces above).
2. `getRoleId(): string` — return the user's primary role string (e.g. `'member'`).
3. `getResourceId(): string` — return a stable identifier for ACL resource
   checks against the user's own profile (typically `'user'`).
4. `getOwnerId(): int|string` — return the user's primary key so that
   `OwnershipAssertion` can compare it against a profile resource's owner.
5. `getDetail(string $name): mixed` — must expose at minimum `store_id` for
   store-scoped ownership assertions.
6. `isGuest(): bool` — `GuestUser` returns `true`; `User` returns `false`.

---

## Checklist

```
□ Concrete User class implements Webware\UserManager\UserInterface
□ GuestUser::isGuest() returns true
□ User::isGuest() returns false
□ MezzioUserInterface::class aliased to UserManagerUserInterface::class in host-app DI
□ UserManagerUserInterface::class bound to the concrete factory in host-app DI
□ getOwnerId() returns the user's PK (not store_id — that comes via getDetail('store_id'))
□ getDetail('store_id') returns an int for StoreOwnedResourceAssertion
```
