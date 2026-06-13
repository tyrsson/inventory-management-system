# `$storeId` Usage Inventory

Generated 2026-06-12. Maps every reference to `$storeId` / `store_id` / `getStoreId()`
across the codebase.

---

## 1. `webware-usermanager` — current User entity & supporting code

| File | Lines | Usage |
|---|---|---|
| `src/webware-usermanager/src/Command/SaveUserCommand.php` | 29 | `public int $storeId,` — command property |
| `src/webware-usermanager/src/CommandHandler/SaveUserHandler.php` | 53 | `'storeId' => $command->storeId,` — inserts into user row |
| `src/webware-usermanager/src/Middleware/RegistrationMiddleware.php` | 44, 66 | Validates `storeId` from POST body; passes `storeId: (int) $body['storeId']` to `SaveUserCommand` |
| `src/webware-usermanager/src/Repository/UserRepository.php` | 93, 99, 100 | `findAll(?int $storeId = null)` — optional `WHERE user.storeId` filter |
| `src/webware-usermanager/src/Repository/UserRepositoryInterface.php` | 44 | Interface declaration: `findAll(?int $storeId = null): ?array` |
| `src/webware-usermanager/templates/user/registration.phtml` | 96 | `<input name="storeId">` — registration form field |
| `src/webware-usermanager/src/Entity/User.php` | — | **NO `$storeId`** — current entity does not have it |

> **⚠ Disconnect:** `SaveUserHandler` writes `'storeId' => $command->storeId` into the user
> row, but the current `User` entity in `webware-usermanager` has no `$storeId` constructor
> property. The column exists in `data/schema/002_user.sql` (line 11:
> `store_id SMALLINT UNSIGNED NOT NULL`), so the handler writes it to the DB but the
> entity cannot receive it.

---

## 2. `ims-store` — old store-scoped User

| File | Lines | Usage |
|---|---|---|
| `src/ims-store/src/Entity/User.php` | 96 | `int\|string\|null $storeId,` — constructor property |
| `src/ims-store/src/Entity/User.php` | 160–162 | `getStoreId(): int` — returns `$this->storeId` |
| `src/ims-store/src/Entity/User.php` | 165 | `withStoreId(int $storeId): self` — immutable setter |
| `src/ims-store/src/Entity/User.php` | 186, 204, 222, 240, 258, 277, 295 | Passed through in `with*` method `new self()` calls |
| `src/ims-store/src/Acl/StoreProprietaryInterface.php` | 14 | `getStoreId(): int` — ACL contract |
| `src/ims-store/src/Acl/StoreOwnedResourceAssertion.php` | 35 | `$resource->getStoreId() === $role->getStoreId()` — store-level ownership check |

---

## 3. `ims-manifest` — manifests are store-scoped

| File | Lines | Usage |
|---|---|---|
| `src/ims-manifest/src/Command/SaveManifestCommand.php` | 18, 29, 31 | `public readonly int $storeId` + `getStoreId()` |
| `src/ims-manifest/src/Entity/Manifest.php` | 35, 53, 118 | `public readonly int $storeId` — used in reference string (`$storeId . '-' . $date->format('md')`) |
| `src/ims-manifest/src/Csv/ParsedManifest.php` | 27 | `public readonly int $storeId` |
| `src/ims-manifest/src/Csv/ManifestCsvParser.php` | 75, 96, 118, 124, 136, 146 | Parses store ID from CSV consignment row |
| `src/ims-manifest/src/Repository/ManifestRepository.php` | 37, 78, 127, 237 | Columns, insert, and hydration via `storeId` |
| `src/ims-manifest/src/Middleware/ProcessManifestUploadMiddleware.php` | 91 | `$parsed->storeId` passed to `SaveManifestCommand` |

---

## 4. Database schema (`data/schema/`)

| File | Column | FK |
|---|---|---|
| `002_user.sql:11` | `store_id SMALLINT UNSIGNED NOT NULL` | → `store(store_number)` |
| `005_manifest.sql:10` | `store_id SMALLINT UNSIGNED NOT NULL` | → `store(store_number)` |
| `007_product.sql:27` | `store_id SMALLINT UNSIGNED NOT NULL` | → `store(store_number)` |
| `010_ticket.sql:12` | `store_id SMALLINT UNSIGNED NOT NULL` | → `store(store_number)` |
| `012_transfer.sql:13-14` | `from_store_id`, `to_store_id` | → `store(store_number)` |
| `999_seed.sql:27` | JSON: `'{"storeId": 207}'` | Seed data |

---

## 5. Migrations (`src/ims-migration/`)

| File | Lines | Usage |
|---|---|---|
| `Migration002User.php` | 69 | Comment: "Plugin extension data - storeId, etc. as JSON" |
| `Migration005Manifest.php` | 47, 78, 79 | `storeId` column + index + FK |
| `Migration007Product.php` | 53, 109, 114 | `storeId` column + index + FK |
| `Migration010Ticket.php` | 48, 94, 95 | `storeId` column + index + FK |
| `Migration012Transfer.php` | 49, 54, 92–94 | `fromStoreId`, `toStoreId` columns + FK |

---

## 6. Tests (`test/`)

| File | Lines | Usage |
|---|---|---|
| `test/AppTest/Acl/StoreOwnershipAssertionPrototypeTest.php` | 48–291, 452, 472 | Extensively mocks `storeId`/`store_id` on user/role/resource doubles for ownership assertion testing |

---

## Key Disconnect

- **`ims-store/src/Entity/User.php`** — the old entity, has `$storeId` as a constructor property
- **`webware-usermanager/src/Entity/User.php`** — the current entity, does **NOT** have `$storeId`
- **`SaveUserCommand`** still has `public int $storeId` and **`SaveUserHandler`** writes it to the DB
- **`UserRepository::findAll()`** still accepts an optional `$storeId` filter
- **`RegistrationMiddleware`** still collects `storeId` from the form
- **`StoreOwnedResourceAssertion`** still calls `$role->getStoreId()` — but the current `User` entity has no `getStoreId()`; the store scope is likely now accessed via `getDetail('store_id')` instead (as indicated in the test comments)
