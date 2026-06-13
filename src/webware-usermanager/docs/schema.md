# `user` Table Schema

> Generated 2026-06-13 from live database via PhpDb MCP.

## Entity-Relationship Diagram

```mermaid
erDiagram
    user {
        int id PK "auto-increment"
        json roleId "NOT NULL"
        varchar(75) firstName "NOT NULL"
        varchar(75) lastName "NOT NULL"
        varchar(255) email "UK, NOT NULL"
        varchar(255) passwordHash "NOT NULL"
        tinyint active "NOT NULL, DEFAULT 0"
        varchar(36) verificationToken "NULL"
        datetime tokenCreatedAt "NULL"
        datetime createdAt "NOT NULL, DEFAULT CURRENT_TIMESTAMP"
        json details "NULL — extensible bag (storeId, etc.)"
    }

    manifest {
        int id PK
        smallint storeId FK
        varchar(100) reference
        date receivedDate
        int createdBy FK "→ user.id"
        datetime created_at
        varchar(255) csvPath
        json params
    }

    manifest_item {
        int id PK
        int manifestId FK
        varchar(20) aoNumber
        mediumint sku FK
        varchar(30) vsn
        varchar(255) specs
        smallint case_qty
        tinyint isDamaged
        text notes
        int scannedBy FK "→ user.id"
        datetime scanned_at
        json params
    }

    ticket {
        int id PK
        smallint storeId FK
        varchar(50) reference
        enum ticketType
        varchar(100) celerantRef
        varchar(200) customerName
        datetime scheduledAt
        enum status "DEFAULT Pending"
        int completedBy FK "→ user.id, NULL"
        datetime completedAt
        int createdBy FK "→ user.id"
        datetime created_at
        json params
    }

    transfer {
        int id PK
        smallint fromStoreId FK
        smallint toStoreId FK
        varchar(50) reference
        text notes
        enum status "DEFAULT Pending"
        int completedBy FK "→ user.id, NULL"
        datetime completedAt
        int createdBy FK "→ user.id"
        datetime created_at
        json params
    }

    user ||--o{ manifest : "createdBy"
    user ||--o{ manifest_item : "scannedBy"
    user ||--o{ ticket : "createdBy"
    user ||--o{ ticket : "completedBy"
    user ||--o{ transfer : "createdBy"
    user ||--o{ transfer : "completedBy"
    manifest ||--o{ manifest_item : "manifestId"
```

## Column Details

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `INT(10) UNSIGNED` | ❌ | auto-increment | Primary key |
| `roleId` | `JSON` | ❌ | — | Array of role identifiers |
| `firstName` | `VARCHAR(75)` | ❌ | — | |
| `lastName` | `VARCHAR(75)` | ❌ | — | |
| `email` | `VARCHAR(255)` | ❌ | — | Unique constraint `uq_user_email` |
| `passwordHash` | `VARCHAR(255)` | ❌ | — | |
| `active` | `TINYINT(3)` | ❌ | `0` | Boolean flag |
| `verificationToken` | `VARCHAR(36)` | ✅ | `NULL` | |
| `tokenCreatedAt` | `DATETIME` | ✅ | `NULL` | |
| `createdAt` | `DATETIME` | ❌ | `CURRENT_TIMESTAMP` | |
| `details` | `JSON` | ✅ | `NULL` | Extensible bag — store-scoped fields (`storeId`, etc.) live here |

## Constraints

| Name | Type | Columns |
|---|---|---|
| `_laminas_user_PRIMARY` | PRIMARY KEY | `id` |
| `_laminas_user_uq_user_email` | UNIQUE | `email` |

## Foreign Key References (inbound)

`user` has **no outbound** foreign keys. The following tables reference `user(id)`:

| Referencing Table | Column | Constraint | ON DELETE |
|---|---|---|---|
| `manifest` | `createdBy` | `fk_manifest_created_by` | NO ACTION |
| `manifest_item` | `scannedBy` | `fk_mi_scanned_by` | NO ACTION |
| `ticket` | `createdBy` | `fk_ticket_created_by` | NO ACTION |
| `ticket` | `completedBy` | `fk_ticket_completed_by` | NO ACTION |
| `transfer` | `createdBy` | `fk_xfer_created_by` | NO ACTION |
| `transfer` | `completedBy` | `fk_xfer_completed_by` | NO ACTION |

## Notes

- **`storeId` is not a column** on `user`. On the old `ims-store` entity it was a first-class property; it now lives inside the JSON `details` column and is accessed via `getDetail('storeId')` by consumers that need store-scoping (e.g., ACL ownership assertions).
- All inbound FKs use `ON DELETE NO ACTION` — users cannot be deleted while referenced by manifests, manifest items, tickets, or transfers.
- The `roleId` column stores roles as a JSON array (e.g., `["Member", "Warehouse"]`). The entity's `getRoles()` and `getRoleId()` methods derive from this field.
