# User Module — Documentation Index

This directory documents the user authentication and account management workflows
implemented in `src/User/`.

---

## Contents

| File | Topic |
|------|-------|
| [registration.md](registration.md) | Self-registration form → inactive account → verification email |
| [email-verification.md](email-verification.md) | Token validation, account activation, TTL expiry |
| [resend-verification.md](resend-verification.md) | Requesting a fresh token without revealing account existence |
| [login.md](login.md) | Login / logout via `LoginMiddleware` + `IdentityMiddleware` |
| [toast-notifications.md](toast-notifications.md) | `ImsMessenger` flash toast system |

---

## High-Level Flow

```
 ┌──────────────┐   POST /register     ┌─────────────────────┐
 │   Browser    │ ──────────────────>  │ RegistrationMiddleware│
 └──────────────┘                      └────────┬────────────┘
                                                │ SaveUserCommand
                                                ▼
                                       ┌─────────────────────┐
                                       │   SaveUserHandler    │
                                       │  (active=0, token)   │
                                       └────────┬────────────┘
                                                │ PostHandleEvent
                                                ▼
                                       ┌─────────────────────┐
                                       │ SendVerificationEmail│
                                       │     Listener         │
                                       └────────┬────────────┘
                                                │ email sent
                                                │
                                       302 → /login (toast)
                                                │
 ┌──────────────┐   GET /verify-email  ┌────────▼────────────┐
 │   Browser    │ ──────────────────>  │  VerifyEmailHandler  │
 └──────────────┘       /{token}       └────────┬────────────┘
                                                │ active=1
                                                │
                                       302 → /login (toast)
                                                │
 ┌──────────────┐   POST /user.manager/login  ┌─────────────────────┐
 │   Browser    │ ──────────────────────────> │   LoginMiddleware    │
 └──────────────┘                             └────────┬────────────┘
                                                       │ UserRepository
                                                       │ ::authenticate()
                                                       │
                                              302 → / (logged in)
```

---

## Module Configuration Keys

| Key | Location | Purpose |
|-----|----------|---------|
| `user.base_url` | `user.global.php` | Base URL for verification links |
| `user.from_email` | `user.global.php` | Sender address for verification emails |
| `user.from_name` | `user.global.php` | Sender display name |
| `user.verification_token_ttl` | `user.global.php` | Token lifetime in seconds (default `86400`) |
| `authentication.redirect` | `Webware\UserManager\ConfigProvider` | Unauthenticated redirect target (`/user.manager/login`) |
| `authentication.username` | `Webware\UserManager\ConfigProvider` | POST field for login identifier (`email`) |
| `authentication.password` | `Webware\UserManager\ConfigProvider` | POST field for credential (`password`) |
| `authentication.post_login_redirect` | `Webware\UserManager\ConfigProvider` | Redirect after successful login (default `'/'`) |

---

## Known Limitations (v0.1.x)

- `headTitle` in `layout/default.phtml` still reads `'Farmers IMS'`.
