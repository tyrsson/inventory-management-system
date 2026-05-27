# Identity Resolution Flow

## Overview

Identity is resolved on every request by `IdentityMiddleware` (global pipeline).
Login credentials are processed by `LoginMiddleware` (route-stack, POST only).
`mezzio/mezzio-authentication-session` (`PhpSession`) is **not used**.

---

## Flow 1 — Login POST

```mermaid
sequenceDiagram
    participant Browser
    participant SessionMW as SessionMiddleware (global)
    participant IdentMW as IdentityMiddleware (global)
    participant LoginMW as LoginMiddleware (route stack)
    participant Repo as UserRepository::authenticate()
    participant Session

    Browser->>SessionMW: POST /user.manager/login {email, password}
    SessionMW->>IdentMW: session started (no user yet)
    IdentMW->>IdentMW: session->get(UserInterface::class) → null
    IdentMW->>IdentMW: userFactory('Guest', [], []) → GuestUser
    Note over IdentMW: withAttribute(UserInterface::class, GuestUser)
    IdentMW->>LoginMW: process(request)
    LoginMW->>Repo: authenticate(email, password)
    Repo->>Repo: findByEmail(email)
    Repo->>Repo: active check + password_verify
    alt auth fails
        Repo-->>LoginMW: null
        LoginMW->>LoginMW: messenger->error(...), pass through
        LoginMW-->>Browser: 200 login form with error toast
    else auth succeeds
        Repo-->>LoginMW: User $user ✅
        LoginMW->>Session: set(UserInterface::class, [username, roles, details])
        LoginMW->>Session: regenerate()
        LoginMW-->>Browser: 302 → post_login_redirect ('/')
    end
```

---

## Flow 2 — Session Restore (subsequent requests)

```mermaid
sequenceDiagram
    participant Browser
    participant SessionMW as SessionMiddleware (global)
    participant IdentMW as IdentityMiddleware (global)
    participant UserFactory as UserFactory closure
    participant AuthMW as AuthorizationMiddleware (global)
    participant Handler

    Browser->>SessionMW: GET /any/route
    SessionMW->>IdentMW: session restored
    IdentMW->>IdentMW: session->get(UserInterface::class) → array{username, roles, details}
    IdentMW->>UserFactory: (username, roles, details)
    Note over UserFactory: isset(details['id'], details['role_id'], details['first_name'])<br/>→ new User(...) ✅
    UserFactory-->>IdentMW: User $user ✅
    IdentMW->>AuthMW: withAttribute(UserInterface::class, User)
    AuthMW->>AuthMW: isAllowedRoute($routeName, $roles)
    alt allowed
        AuthMW->>Handler: dispatch
    else denied
        AuthMW->>Handler: ForbiddenHandler
    end
```

---

## UserFactory Discriminator

`UserFactory::__invoke()` returns a closure with signature:

```php
function (string $identity, array $roles = [], array $details = []): UserInterface
```

The closure checks for authenticated-user markers in `$details`:

```php
if (isset($details['id'], $details['role_id'], $details['first_name'])) {
    return new User(
        $details['id'],
        $details['store_id'],
        $details['role_id'],
        $details['first_name'],
        ...
        $identity,   // email
        $roles,
    );
}
return new GuestUser($identity, $roles ?: [GuestUser::GUEST_ROLE], $details);
```

When `LoginMiddleware` writes session details the full user row is included, so
subsequent calls by `IdentityMiddleware` always hit the `User` branch.

---

## Session Key and Data Shape

Key: `Webware\UserManager\UserInterface::class`

```php
[
    'username' => $user->getIdentity(),   // email
    'roles'    => $user->getRoles(),      // e.g. ['Member']
    'details'  => [
        'id', 'store_id', 'role_id',
        'first_name', 'last_name',
        'active', 'created_at',
        'verification_token', 'token_created_at',
        'password_hash',
    ],
]
```

`IdentityMiddleware` checks `is_array($userInfo) && isset($userInfo['username'])`
before passing to the factory.

---

## File Reference

| File | Responsibility |
|---|---|
| `src/webware-usermanager/src/Middleware/LoginMiddleware.php` | Credential check, session write, redirect |
| `src/webware-usermanager/src/Middleware/Container/LoginMiddlewareFactory.php` | Wires repository, messenger, config |
| `src/webware-acl/src/Middleware/IdentityMiddleware.php` | Session restore, User/GuestUser on request |
| `src/webware-acl/src/Container/IdentityMiddlewareFactory.php` | Injects userFactory callable only |
| `src/webware-usermanager/src/Container/UserFactory.php` | Returns discriminating closure |
| `src/webware-usermanager/src/Repository/UserRepository.php` | `authenticate()` returns `(User&UserInterface)|null` — no factory call |

---

## Historical Note

Prior to 2026-05-22 this file documented three bugs where `UserFactory` always
returned `GuestUser` regardless of authentication state. All three bugs are
resolved. See git history for the original analysis.
