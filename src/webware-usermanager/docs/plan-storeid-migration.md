# Plan: Migrate storeId from Dedicated Column to params JSON

## TL;DR

`storeId` must move from a dedicated `user` table column into the `params` JSON column so `webware-usermanager` stays vendor-clean (no store awareness). `ims-store` reads/writes `storeId` from `params['storeId']` via its own entity and hydrates it during `withRowData()`.

---

## Phase 1 — DB Migration (no dependencies)

**TASK-001**: Create new migration `Migration003StoreIdToParams` at `src/ims-migration/src/`
- Step number: 3
- `up()`:
  1. `UPDATE user SET params = JSON_SET(COALESCE(params, '{}'), '$.storeId', CAST(storeId AS UNSIGNED))` — move existing data
  2. `ALTER TABLE user DROP FOREIGN KEY fk_user_store` — drop FK first
  3. `ALTER TABLE user DROP COLUMN storeId` — drop column
- `down()`: reverse (add column back, extract from params)

**TASK-002**: Update `data/schema/002_user.sql` — remove `store_id` column and FK constraint; `params` column already exists

**TASK-003**: Update `data/schema/999_seed.sql`
- Add `params` to the INSERT column list: `(params, roleId, firstName, lastName, email, passwordHash, active)`
- Replace `storeId` column value with `JSON_OBJECT('storeId', 207)` as the first VALUES entry
- Remove `storeId = VALUES(storeId)` from ON DUPLICATE KEY UPDATE
- Add `params = VALUES(params)` to ON DUPLICATE KEY UPDATE
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

## Phase 3 — ims-store adapts to params-based storeId

| Task | File | Action |
|------|------|--------|
| TASK-011 | `Entity/User.php` | Add `withRowData()` override — decodes `params` JSON from DB row, extracts `storeId` into a private backing field; `getStoreId()` reads from that backing field. Remove the constructor's `$storeId` parameter — it's no longer a DB column |
| TASK-012 | `Container/UserInterfaceFactory.php` | `storeId` detected in session `details` → inject into `params` array passed to constructor, not as direct constructor arg |
| TASK-013 | `ConfigProvider.php` | Register a listener for a `PostLoginEvent` (or similar) that reads `$user->getStoreId()` and adds it to the session details as `storeId` |

---

## Phase 4 — Registration Flow (ims-store overrides)

| Task | File | Action |
|------|------|--------|
| TASK-014 | `ims-store` creates `Ims\Store\Command\SaveStoreUserCommand` with `$storeId` — this is the ims-store-aware variant of `SaveUserCommand` |
| TASK-015 | `ims-store` creates `Ims\Store\Middleware\ProcessStoreRegistrationMiddleware` — reads `storeId` from form, builds `SaveStoreUserCommand`, writes `storeId` into `params` JSON on save |

---

## Decisions

- **`params` column already exists** in both migration and schema — no need to create it
- **`LoginMiddleware` removal**: Instead of checking `$user->storeId` directly (which no longer exists on the base entity), `ims-store` listens to a post-login event and adds `storeId` to the session details
- **`StoreUser::getStoreId()`** reads from a private `$storeId` property populated during `withRowData()` via `json_decode($row['params'])['storeId']`
- **Registration**: `webware-usermanager` handles basic registration (name, email, password). `ims-store` extends it with a store-select step that writes `storeId` into `params`
- **`findAll()` store filter**: Removed from `webware-usermanager`. If needed, `ims-store` provides its own repository extension with a JSON path filter: `WHERE JSON_EXTRACT(params, '$.storeId') = ?`

## Verification

1. Run migration — verify `storeId` column gone, `params` JSON contains `{"storeId": N}` for each user
2. Login as a user — confirm session `details` still include `storeId` (added by ims-store event listener)
3. `StoreOwnedResourceAssertion` still works — `$role->getStoreId()` returns correct value from params
4. Registration with store selection works — storeId written to `params` JSON
5. `webware-usermanager` has zero references to `storeId` (grep confirms)
