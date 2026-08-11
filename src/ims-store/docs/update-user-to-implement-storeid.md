# Plan: Migrate storeId from Dedicated Column to details JSON

## TL;DR

`storeId` must move from a dedicated `user` table column into the `details` JSON column so `webware-usermanager` stays vendor-clean (no store awareness). `ims-store` reads/writes `storeId` from `details['storeId']` via its own entity and hydrates it during `withRowData()`.

---

## Phase 1 — DB Migration (no dependencies)

**TASK-001**: Create new migration `Migration003StoreIdToDetails` at `src/ims-migration/src/`
- Step number: 3
- `up()`:
  1. `UPDATE user SET details = JSON_SET(COALESCE(details, '{}'), '$.storeId', CAST(storeId AS UNSIGNED))` — move existing data
  2. `ALTER TABLE user DROP FOREIGN KEY fk_user_store` — drop FK first
  3. `ALTER TABLE user DROP COLUMN storeId` — drop column
- `down()`: reverse (add column back, extract from details)

**TASK-002**: Update `data/schema/002_user.sql` — remove `store_id` column and FK constraint; `details` column already exists

**TASK-003**: Update `data/schema/999_seed.sql`
- Add `details` to the INSERT column list: `(details, roleId, firstName, lastName, email, passwordHash, active)`
- Replace `storeId` column value with `JSON_OBJECT('storeId', 207)` as the first VALUES entry
- Remove `storeId = VALUES(storeId)` from ON DUPLICATE KEY UPDATE
- Add `details = VALUES(details)` to ON DUPLICATE KEY UPDATE
- Pattern: same as `roleId` — just a JSON column in the INSERT

---

## Phase 2 — Purging storeId from webware-usermanager

| Task | File | Action |
|------|------|--------|
| TASK-004 | `Entity/User.php` | Remove `withStoreId()` method (line ~175) |
| TASK-005 | `Command/SaveUserCommand.php` | Remove `public int $storeId` constructor property (line 29) |
| TASK-006 | `CommandHandler/SaveUserHandler.php` | Remove `'storeId' => $command->storeId` from insert data (line ~53) |
| TASK-007 | `Middleware/RegistrationMiddleware.php` | Remove `storeId` from field list (line 44) and from `new SaveUserCommand` args (line 66) |
| TASK-008 | `Middleware/LoginMiddleware.php` | Remove `'store_id' => $user->storeId` from session details (line ~69). Replace with: add a `PostLoginEvent` or dispatch an event that `ims-store` listens to for adding storeId to session |
| TASK-009 | `Repository/UserRepository.php` | Remove `?int $storeId = null` parameter from `findAll()` and the `WHERE user.storeId` clause (lines 92, 98-99) |
| TASK-010 | `Repository/UserRepositoryInterface.php` | Remove `?int $storeId = null` from `findAll()` signature (line 44) |

---

## Phase 3 — ims-store adapts to details-based storeId

| Task | File | Action |
|------|------|--------|
| TASK-011 | `Entity/User.php` | Add `withRowData()` override — decodes `details` JSON from DB row, extracts `storeId` into a private backing field; `getStoreId()` reads from that backing field. Remove the constructor's `$storeId` parameter — it's no longer a DB column |
| TASK-012 | `Container/UserInterfaceFactory.php` | `storeId` detected in session `details` → inject into `details` array passed to constructor, not as direct constructor arg |
| TASK-013 | `ConfigProvider.php` | Register a listener for a `PostLoginEvent` (or similar) that reads `$user->getStoreId()` and adds it to the session details as `storeId` |

---

## Phase 4 — Registration Flow (ims-store overrides)

| Task | File | Action |
|------|------|--------|
| TASK-014 | `ims-store` creates `Ims\Store\Command\SaveStoreUserCommand` with `$storeId` — this is the ims-store-aware variant of `SaveUserCommand` |
| TASK-015 | `ims-store` creates `Ims\Store\Middleware\ProcessStoreRegistrationMiddleware` — reads `storeId` from form, builds `SaveStoreUserCommand`, writes `storeId` into `details` JSON on save |

---

## Decisions

- **`details` column already exists** in both migration and schema — no need to create it
- **`LoginMiddleware` removal**: Instead of checking `$user->storeId` directly (which no longer exists on the base entity), `ims-store` listens to a post-login event and adds `storeId` to the session details
- **`StoreUser::getStoreId()`** reads from a private `$storeId` property populated during `withRowData()` via `json_decode($row['details'])['storeId']`
- **Registration**: `webware-usermanager` handles basic registration (name, email, password). `ims-store` extends it with a store-select step that writes `storeId` into `details`
- **`findAll()` store filter**: Removed from `webware-usermanager`. If needed, `ims-store` provides its own repository extension with a JSON path filter: `WHERE JSON_EXTRACT(details, '$.storeId') = ?`

## Verification

1. Run migration — verify `storeId` column gone, `details` JSON contains `{"storeId": N}` for each user
2. Login as a user — confirm session `details` still include `storeId` (added by ims-store event listener)
3. `StoreOwnedResourceAssertion` still works — `$role->getStoreId()` returns correct value from details
4. Registration with store selection works — storeId written to `details` JSON
5. `webware-usermanager` has zero references to `storeId` (grep confirms)
