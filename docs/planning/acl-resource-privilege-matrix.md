# ACL Resource / Privilege / Role Matrix

_Authored: May 13, 2026_
_Branch: `acl-ownership-assertion-aggregates`_

> **⚠ LIVING DOCUMENT**
> This file is the source of truth for ACL planning decisions made in conversation.
> **Never remove or shorten existing content.** Append new dated entries for any changes.
> When implementation diverges from this plan, update this file first.

---

## 1. Design Principles — Confirmed

### CRUD verbs over domain-specific privilege names

**Rule: ACL privileges express authorization boundaries, not UI workflows.**

The test for a new privilege: *"Does this create a genuinely different authorization
profile that a standard CRUD verb cannot express?"* If the answer is no, use CRUD.

**Dropped domain-specific privileges (confirmed May 13, 2026):**

| UI action | Previously proposed privilege | Correct privilege |
|---|---|---|
| Upload manifest | `upload` | `create` on `manifest` |
| Process manifest | `process` | `update` on `manifest` + `create` on `product` |
| Flag damage | `flag-damage` | `update` on `product` |
| Resolve PQA | `resolve-pqa` | `update` on `product` |
| Send images to PQA | *(none proposed)* | `create` on `product_image` |

The UI may present these as named workflows. The ACL does not mirror UI terminology.

---

## 2. Role Hierarchy

Confirmed from `data/schema/999_seed.sql` and must-have requirements.

```
guest
  └─ member
       ├─ Sales
       └─ Warehouse
            └─ Warehouse Supervisor
                 ├─ Credit Manager
                 └─ DC Warehouse
                      └─ Manager
                           └─ Administrator
                                └─ Developer
```

### Open question — DC Warehouse mutation access

**Problem:** `DC Warehouse` sits above `Warehouse Supervisor` in the hierarchy and therefore
inherits store-scoped mutation grants (`create`, `update`, `delete` on store resources).
DC Warehouse staff should only have read access across all stores — no mutations.

**Decision (confirmed May 13, 2026):** Option 1 — explicit `deny` rules on `DC Warehouse`.

Empirical tests confirmed assertions ARE inherited through the role DAG (see §7).
Therefore:
- All store-scoped `create`/`update` grants on `member` propagate with their assertions to
  all descendant roles automatically.
- `DC Warehouse` gets explicit `deny` overrides for `create`, `update`, `delete` on all
  store-scoped resources, blocking the inherited mutation grants cleanly.
- Hierarchy restructure is not needed.

---

## 3. Resource Registry

### Store-scoped resources (require `StoreOwnedResourceAssertion` on mutations)

| Resource ID | Label | `acl_resource` seeded? |
|---|---|---|
| `manifest` | Manifest | ❌ not yet |
| `product` | Product | ❌ not yet |
| `product_image` | Product Image | ❌ not yet |
| `ticket` | Ticket | ❌ not yet |
| `transfer` | Transfer | ❌ not yet |
| `store.settings` | Store Settings | ❌ not yet |

### Global resources (no store assertion)

| Resource ID | Label | `acl_resource` seeded? |
|---|---|---|
| `public` | Public | ✅ seeded |
| `user` | User | ✅ seeded |
| `dashboard` | Dashboard | ✅ seeded |
| `admin.user` | Admin — User Management | ✅ seeded |
| `sku_catalogue` | SKU Catalogue | ❌ not yet |
| `major_code` | Major Code | ❌ not yet |

---

## 4. Privilege Matrix

Standard CRUD verbs apply to all resources unless noted.

### Store-scoped resources — `manifest`, `product`, `product_image`, `ticket`, `transfer`

| Privilege | Lowest role granted | Needs `StoreOwnedResourceAssertion`? | Notes |
|---|---|---|---|
| `read` | `member` | ❌ | Cross-store read is intentional — enables inter-store transfer decisions |
| `create` | `member` | ✅ | Applies to all five resources |
| `update` | `member` | ✅ | Applies to all five resources |
| `delete` | `Warehouse Supervisor` | ✅ | Lower roles cannot delete |

### `store.settings`

| Privilege | Lowest role granted | Needs assertion? | Notes |
|---|---|---|---|
| `read` | `Manager` | ❌ | Managers see their own store's settings |
| `update` | `Manager` | ✅ | Must be own store only |

No `create` or `delete` on `store.settings` — settings rows are created with the store.

### Global resources — `sku_catalogue`, `major_code`

| Privilege | Lowest role granted | Needs assertion? | Notes |
|---|---|---|---|
| `read` | `member` | ❌ | All authenticated users can browse catalogue |
| `create` | `Administrator` | ❌ | Most records created via manifest import |
| `update` | `Administrator` | ❌ | |
| `delete` | `Administrator` | ❌ | |

Managers cannot create or update SKU catalogue or major codes. Only Administrator+.

---

## 5. Read Access — Cross-Store Policy (Confirmed)

> "Employees being able to see the images of a product's damage is one of the primary drivers
> for this application."

**All `read` operations on store-scoped resources are granted to `member` without
`StoreOwnedResourceAssertion`.** Any authenticated user can see inventory, damage photos,
and product status from any store. This is intentional and required — it enables:

- Inter-store transfer decisions based on actual product condition
- Prevents wasted trips to pick up visibly unviable product
- Cross-store PQA coordination

The store ownership boundary applies only to **mutations** (`create`, `update`, `delete`).

---

## 6. Assertion Strategy (Confirmed)

### `StoreOwnedResourceAssertion` — listener-driven

Applied via `RegisterOwnershipAssertionListener` on `AclBuiltEvent`.
Single bulk `allow()` call covers all store-scoped roles, resources, and mutating privileges.

```php
// Pseudocode — exact implementation in RegisterOwnershipAssertionListener

$storeResources = ['manifest', 'product', 'product_image', 'ticket', 'transfer'];

// member (and all descendants) — own-store mutations only
$event->acl->allow(
    'member',
    $storeResources,
    ['create', 'update'],
    new StoreOwnedResourceAssertion(),
);

// Warehouse Supervisor (and above) — own-store delete
$event->acl->allow(
    'Warehouse Supervisor',
    $storeResources,
    ['delete'],
    new StoreOwnedResourceAssertion(),
);

// DC Warehouse — read-only; block all inherited mutation grants
$event->acl->deny(
    'DC Warehouse',
    $storeResources,
    ['create', 'update', 'delete'],
);

// Manager — own store settings only
$event->acl->allow(
    'Manager',
    ['store.settings'],
    ['update'],
    new StoreOwnedResourceAssertion(),
);

// Administrator — unrestricted by store boundary (own explicit allow overrides inherited assertion)
$event->acl->allow(
    'Administrator',
    array_merge($storeResources, ['store.settings']),
    ['create', 'update', 'delete'],
);
```

> DC Warehouse mutation grants resolved — see §2. `DC Warehouse` receives explicit deny
> overrides in the listener (see updated pseudocode below).

### `OwnershipAssertion` — DB-driven

Applied to the single `member → user → update` rule via `acl_rule_assertion` table.
Seeded in `data/schema/999_seed.sql`. `AclBuilder::buildAssertion()` attaches at runtime.

---

## 7. Assertion Inheritance — Verification Required

**Question:** When a child role inherits a rule from a parent that has an assertion
attached, does the assertion also fire when the child role is checked?

**From Laminas ACL source review (`Acl.php`):** `isAllowed()` performs depth-first traversal
of the role DAG. When a matching rule is found it evaluates that rule's attached assertion.
The assertion is bound to the specific role's rule. A child role's own explicit rule (if
present) would have no assertion unless one was explicitly added.

**The specific gap:** if `member → manifest → update` is granted with `StoreOwnedResourceAssertion`
and `Warehouse Supervisor` inherits from `Warehouse` which inherits from `member`, does
`isAllowed('Warehouse Supervisor', 'manifest', 'update')` fire the assertion from the `member`
rule, or does it find no explicit rule on `Warehouse Supervisor` and stop?

**Empirically confirmed May 13, 2026** — all 11 tests pass in
`test/AppTest/Acl/StoreOwnershipAssertionPrototypeTest.php`.

### Results

| # | Test | Result |
|---|---|---|
| 1 | `member` own-store → allow | ✅ pass |
| 2 | `member` foreign-store → deny | ✅ pass |
| 3 | `Warehouse` (child) own-store → assertion fires, allow | ✅ pass |
| 4 | `Warehouse` (child) foreign-store → assertion fires, deny | ✅ pass |
| 5 | `Warehouse Supervisor` (grandchild) own-store → assertion fires, allow | ✅ pass |
| 6 | `Warehouse Supervisor` (grandchild) foreign-store → assertion fires, deny | ✅ pass |
| 7 | `Administrator` explicit unrestricted allow → overrides inherited assertion | ✅ pass |

**Conclusion:** Laminas ACL DFS traversal finds the matching rule on the nearest ancestor
and evaluates that rule's assertion. Assertions propagate through the role hierarchy
automatically. A single `allow()` on `member` with `StoreOwnedResourceAssertion` is
all that is needed for the entire Warehouse/Supervisor/Manager/Administrator chain.

Explicit `allow()` without an assertion on a descendant role (e.g. `Administrator`)
properly overrides the inherited asserted rule — the ancestor assertion does NOT fire.

---

## 8. Transfer Exception (Confirmed)

**Do NOT apply `StoreOwnedResourceAssertion` to transfer commands.**

During a transfer the product's `store_id` changes from source → destination by design.
The assertion `user.store_id === resource.getOwnerId()` will deny for the destination store.

Transfer commands require a dedicated `TransferAuthorityAssertion` that asserts the user
has authority over the **source** store only.

See: `transfer-workflow-concerns.md` in session memory.

---

## 9. `ResourceId` Enum (Planned)

Resource identifier strings (e.g. `'manifest'`, `'store.settings'`) will be replaced
with a backed PHP Enum (`ResourceId::Manifest->value`) before the resource list grows
large. All new commands should be written to avoid scattering the string literal —
assign to a local variable or use a constant so migration is a single-point change.

---

## 10. Implementation Checklist

### Before writing any listener code

- [x] Resolve DC Warehouse mutation access — explicit deny on `DC Warehouse` (May 13, 2026)
- [x] Expand `StoreOwnershipAssertionPrototypeTest` with inheritance cases (§7)
- [x] Verify assertion inheritance behaviour — confirmed, assertions ARE inherited (May 13, 2026)

### Seed additions needed (`999_seed.sql`)

- [x] Add `manifest`, `product`, `product_image`, `ticket`, `transfer`, `store.settings` to `acl_resource` (May 13, 2026)
- [x] Add standard CRUD privileges for each new resource (May 13, 2026)
- [x] Add `read` grants for `member` on all store-scoped resources (no assertion) (May 13, 2026)
- [x] Add `read` grant for `Manager` on `store.settings` (no assertion) (May 13, 2026)
- [x] Add `read` grant for `member` on `sku_catalogue`, `major_code` (May 13, 2026)
- [x] Add CRUD grants for `Administrator` on `sku_catalogue`, `major_code` (May 13, 2026)

### Listener implementation (`RegisterOwnershipAssertionListener`)

- [x] Un-deprecate; remove no-op comment (May 13, 2026)
- [x] Implement bulk `allow()` calls per §6 pseudocode (May 13, 2026)
- [ ] Write unit test

### CommandBus integration

- [ ] Wire `HandleAccessDeniedTrait` into each `Process{Action}Middleware` that dispatches
  an ownership-capable command
- [ ] Write unit tests for `OwnershipAssertion`, `StoreOwnedResourceAssertion`,
  and `CommandHandlerMiddleware` override
