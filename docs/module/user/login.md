# Login / Logout Workflow

## Overview

Authentication is handled by `LoginMiddleware` (a route-stack middleware on
`POST /user.manager/login`). Credentials are verified against the database.
Only **active** accounts may log in. Sessions are stored via
`mezzio/mezzio-session`. Identity is restored on every subsequent request by
`IdentityMiddleware` in the global pipeline.

`mezzio/mezzio-authentication-session` (`PhpSession`) is **not used** in the
active authentication flow.

---

## Routes

| Method | Path                    | Route name                       | Middleware stack                                                   |
|--------|-------------------------|----------------------------------|--------------------------------------------------------------------|
| GET    | `/user.manager/login`   | `user.manager.session.read`      | `DisableBodyMiddleware` → `LoginHandler`                          |
| POST   | `/user.manager/login`   | `user.manager.session.create`    | `DisableBodyMiddleware` → `LoginMiddleware` → `LoginHandler`      |
| GET    | `/user.manager/logout`  | `user.manager.logout.read`       | `LogoutHandler`                                                   |

---

## Global Pipeline (identity-related)

```
SessionMiddleware       — starts / restores PHP session
IdentityMiddleware      — reads session, attaches User or GuestUser to request
ImsMessengerMiddleware  — attaches SystemMessengerInterface to request
...
RouteMiddleware
AuthorizationMiddleware — ACL route access check
DispatchMiddleware
```

---

## Login Workflow Diagram

```
Browser                         Server
  |                                |
  |--- GET /user.manager/login --->|
  |                                |
  |               [IdentityMiddleware — global]
  |               - No session data → GuestUser on request
  |               |
  |               [AuthorizationMiddleware — global]
  |               - Guest allowed for session.read → pass through
  |               |
  |               [LoginHandler]
  |               - $user->isGuest() === true → render form
  |<-- 200 login form --------------|
  |                                |
  |--- POST /user.manager/login -->|
  |     {email, password}          |
  |                                |
  |               [IdentityMiddleware — global]
  |               - No session data → GuestUser on request
  |               |
  |               [AuthorizationMiddleware — global]
  |               - Guest allowed for session.create → pass through
  |               |
  |               [DisableBodyMiddleware]
  |               - Disables HTMX body template layer (not parsed body)
  |               |
  |               [LoginMiddleware]
  |               - Reads email + password from parsed body
  |               - Calls UserRepository::authenticate()
  |               |
  |               [UserRepository::authenticate()]
  |               - SELECT user by email
  |               - Verify active === true
  |               - password_verify(password, hash)
  |               |
  |               [auth fails]
  |               - Logs failed attempt
  |               - SystemMessengerInterface::error() toast
  |               - Passes through to LoginHandler
  |               |
  |               [LoginHandler — POST failure]
  |               - $user->isGuest() === true → re-render form with toasts
  |<-- 200 login form + error toast |
  |                                |
  |               [auth succeeds]
  |               - RetrieveSession::fromRequest()
  |               - session->set(UserInterface::class, [...])
  |               - session->regenerate()
  |<-- 302 / (post_login_redirect) |
```

---

## Session Restore Diagram (subsequent requests)

```
Browser                         Server
  |                                |
  |--- GET /any/protected/route -->|
  |                                |
  |               [SessionMiddleware]
  |               - Restores PHP session
  |               |
  |               [IdentityMiddleware]
  |               - session->get(UserInterface::class) → array with username/roles/details
  |               - Calls UserFactory closure → new User(...)
  |               - withAttribute(UserInterface::class, $user)
  |               |
  |               [AuthorizationMiddleware]
  |               - $user->isGuest() === false
  |               - ACL check → allowed → dispatch
```

---

## Logout Workflow Diagram

```
Browser                         Server
  |                                |
  |--- GET /user.manager/logout -->|
  |                                |
  |               [LogoutHandler]
  |               - session->clear()
  |<-- 302 /user.manager/login ----|
```

---

## Key Classes

| Class | Namespace | Responsibility |
|-------|-----------|----------------|
| `LoginMiddleware` | `Webware\UserManager\Middleware` | POST handler: authenticate credentials, write session, redirect |
| `LoginHandler` | `Webware\UserManager\RequestHandler` | Render login form (GET + POST failure) |
| `LogoutHandler` | `Webware\UserManager\RequestHandler` | Clear session, redirect to login |
| `IdentityMiddleware` | `Webware\Acl\Middleware` | Global: restore `User` or `GuestUser` from session |
| `UserRepository` | `Webware\UserManager\Repository` | `authenticate(string $email, string $password): (User&UserInterface)|null` |
| `UserFactory` | `Webware\UserManager\Container` | Callable: constructs `User` from session details or `GuestUser` |

---

## Authentication Configuration

Registered in `Webware\UserManager\ConfigProvider::getAuthenticationConfig()`,
stored in the container under `authentication`:

```php
'authentication' => [
    'redirect'           => '/user.manager/login',
    'username'           => 'email',
    'password'           => 'password',
    'post_login_redirect' => '/',          // LoginMiddleware redirect on success
],
```

`LoginMiddlewareFactory` reads `authentication[post_login_redirect]` with
fallback to `Configuration::POST_LOGIN_REDIRECT_VALUE` (`'/'`).

---

## Session Data Written by LoginMiddleware

Stored under the key `Webware\UserManager\UserInterface::class`:

```php
[
    'username' => $user->getIdentity(),     // email address
    'roles'    => $user->getRoles(),        // ['Member'] etc.
    'details'  => [
        'id', 'store_id', 'role_id', 'first_name', 'last_name',
        'active', 'created_at', 'verification_token', 'token_created_at',
        'password_hash',
    ],
]
```

`IdentityMiddleware` reads this key on every subsequent request and passes
the data to the `UserFactory` closure, which discriminates on the presence of
`details['id']`, `details['role_id']`, and `details['first_name']` to decide
whether to construct a `User` or a `GuestUser`.

---

## ACL Resources

| Resource | Allowed roles |
|---|---|
| `user.manager.session.read` | Guest |
| `user.manager.session.create` | Guest |
| `user.manager.logout.read` | Member |

`Member` is denied `session.read` and `session.create` (logged-in users
cannot see the login form).

