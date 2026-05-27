# Migrations Layer — Full Context & Implementation Plan
## Date: 2026-05-13 | Branch: acl-ownership-assertion-aggregates

---

## 1. WHY WE NEED A MIGRATIONS LAYER

### Root Cause of the Current Bug
`bin/db-seed` calls `$adapter->query($sql, QUERY_MODE_EXECUTE)` per file.
PDO's underlying exec/query only executes the **first** SQL statement in a
multi-statement string and silently ignores the rest.

`data/schema/023a_acl_resource_system_col.sql` (renamed from 023) contains
multiple `ALTER TABLE` statements separated by `;`. Only the first statement
(`ALTER TABLE acl_resource ADD COLUMN system ...`) ever ran. The five FK
`DROP`/`ADD CONSTRAINT ... ON DELETE CASCADE` statements were silently skipped.

This caused `deleteResource()` to fail with:
```
SQLSTATE[23000]: 1451 - Cannot delete or update a parent row:
a foreign key constraint fails (fk_priv_resource ON DELETE NO ACTION)
```

### Live DB Fix (Already Applied)
The CASCADE constraints were applied manually in the current session via
10 separate `$adapter->getDriver()->getConnection()->execute(...)` calls:
```
acl_privilege.fk_priv_resource        → ON DELETE CASCADE ✓
acl_rule.fk_rule_resource              → ON DELETE CASCADE ✓
acl_rule.fk_rule_privilege             → ON DELETE CASCADE ✓
acl_route_privilege.fk_rp_resource     → ON DELETE CASCADE ✓
acl_route_privilege.fk_rp_privilege    → ON DELETE CASCADE ✓
```
Delete flow is working in the live DB RIGHT NOW. The fix must survive `db-seed`.

---

## 2. CURRENT SCHEMA FILE STATE

```
data/schema/
  001–022   ← Single-statement CREATE TABLE files. Work fine with db-seed.
  023a_acl_resource_system_col.sql  ← Renamed from 023. STILL multi-statement.
                                       STILL broken under db-seed.
  999_seed.sql  ← DML seeds (also runs every time)
```

`023a` currently contains:
1. `ALTER TABLE acl_resource ADD COLUMN system TINYINT(1) ...` (idempotent, fails silently if col exists)
2. `ALTER TABLE acl_privilege DROP FOREIGN KEY fk_priv_resource` (never runs)
3. `ALTER TABLE acl_privilege ADD CONSTRAINT ...ON DELETE CASCADE` (never runs)
4–11. Similar DROP/ADD pairs for acl_rule (×2) and acl_route_privilege (×2)

---

## 3. THE MIGRATIONS LAYER — ARCHITECTURE PLAN

### 3a. What to Build

A lightweight PHP migrations system. NOT a framework (no Phinx, no Doctrine Migrations). Just:
- A `MigrationInterface`
- Migration classes in `data/migrations/`
- A `schema_migrations` tracking table (created automatically)
- A `bin/migrate` runner script

### 3b. MigrationInterface
```php
namespace App\Migration;

use PhpDb\Adapter\AdapterInterface;

interface MigrationInterface
{
    public function getVersion(): int;         // e.g. 23 — matches schema file numbering
    public function getDescription(): string; // human-readable
    public function up(AdapterInterface $adapter): void;
    public function down(AdapterInterface $adapter): void; // optional/noop
}
```

### 3c. Tracking Table
Table: `schema_migrations`
```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version      INT UNSIGNED NOT NULL,
    description  VARCHAR(255) NOT NULL,
    migrated_at  DATETIME     NOT NULL,
    PRIMARY KEY (version)
);
```
Runner creates this table on first run using phpdb DDL:
```php
$create = new CreateTable('schema_migrations');
$create->addColumn(new Column\Integer('version', ['unsigned' => true]));
$create->addColumn(new Column\Varchar('description', 255));
$create->addColumn(new Column\Datetime('migrated_at'));
$create->addConstraint(new PrimaryKey('version'));
$adapter->query($sql->buildSqlString($create), AdapterInterface::QUERY_MODE_EXECUTE);
```

### 3d. Directory Structure
```
data/migrations/
    MigrationInterface.php
    Migration023AclResourceSystemColumn.php
    (future migrations here)
```
Class naming: `Migration{NNN}{PascalDescription}` in namespace `App\Migration`.

### 3e. phpdb DDL Execution Pattern
**CRITICAL**: Do NOT chain `dropConstraint()` + `addConstraint()` on the same
`AlterTable` object. The spec array renders ADD_CONSTRAINTS before DROP_CONSTRAINTS
— wrong order, MySQL rejects it. Use **separate objects and separate query calls**:

```php
use PhpDb\Sql\Sql;
use PhpDb\Sql\Ddl\AlterTable;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;
use PhpDb\Adapter\AdapterInterface;

$sql = new Sql($adapter);

// Step 1: DROP (separate AlterTable)
$drop = new AlterTable('acl_privilege');
$drop->dropConstraint('fk_priv_resource');
$adapter->query($sql->buildSqlString($drop), AdapterInterface::QUERY_MODE_EXECUTE);

// Step 2: ADD with CASCADE (separate AlterTable)
$add = new AlterTable('acl_privilege');
$add->addConstraint(new ForeignKey(
    'fk_priv_resource',  // constraint name
    'resource_pk',       // local column
    'acl_resource',      // reference table
    'resource_pk',       // reference column
    'CASCADE',           // onDelete
    'NO ACTION'          // onUpdate
));
$adapter->query($sql->buildSqlString($add), AdapterInterface::QUERY_MODE_EXECUTE);
```

MySQL 8.4.9 supports `DROP CONSTRAINT` as alias for `DROP FOREIGN KEY` ✓
Verified output from `buildSqlString()` on a drop-only AlterTable:
```sql
ALTER TABLE `acl_privilege`
 DROP CONSTRAINT `fk_priv_resource`
```

### 3f. bin/migrate Runner Script Logic
1. Boot container, get `AdapterInterface`
2. `CREATE TABLE IF NOT EXISTS schema_migrations` (via phpdb DDL `CreateTable`)
3. `SELECT version FROM schema_migrations` → build set of already-run versions
4. Glob `data/migrations/Migration*.php`, require each, collect instances of `MigrationInterface`
5. Sort by `getVersion()` ASC
6. For each migration where version NOT IN already-run:
   - Print `  RUN   vNNN description`
   - Call `$migration->up($adapter)` inside try/catch
   - On success: INSERT row into schema_migrations, print `  OK`
   - On failure: print error, `exit(1)` — do NOT continue (preserve atomicity per version)
7. Print summary: `Done. N migrated, N skipped, N error(s).`

### 3g. What Happens to db-seed
`bin/db-seed` stays for initial/CI fresh installs — runs 001–022 CREATE TABLE files.
`bin/migrate` runs after db-seed for all alterations.

`023a` SQL file: reduce to a single `ADD COLUMN IF NOT EXISTS` only — so a fresh
`db-seed` creates the column. The migration class handles the FK CASCADE changes.

---

## 4. MIGRATION 023 — EXACT IMPLEMENTATION

File: `data/migrations/Migration023AclResourceSystemColumn.php`

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Sql;
use PhpDb\Sql\Ddl\AlterTable;
use PhpDb\Sql\Ddl\Constraint\ForeignKey;

final class Migration023AclResourceSystemColumn implements MigrationInterface
{
    public function getVersion(): int
    {
        return 23;
    }

    public function getDescription(): string
    {
        return 'acl_resource system column + FK ON DELETE CASCADE';
    }

    public function up(AdapterInterface $adapter): void
    {
        $sql = new Sql($adapter);

        // ADD COLUMN system — idempotent via IF NOT EXISTS (MySQL 8.0+)
        // Raw DDL string is acceptable; phpdb builder does not support IF NOT EXISTS
        $adapter->getDriver()->getConnection()->execute(
            'ALTER TABLE `acl_resource` ADD COLUMN IF NOT EXISTS `system` TINYINT(1) NOT NULL DEFAULT 0'
        );

        $fks = [
            ['table' => 'acl_privilege',      'name' => 'fk_priv_resource',  'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_rule',            'name' => 'fk_rule_resource',  'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_rule',            'name' => 'fk_rule_privilege', 'col' => 'privilege_pk', 'ref' => 'acl_privilege', 'refcol' => 'privilege_pk'],
            ['table' => 'acl_route_privilege', 'name' => 'fk_rp_resource',    'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_route_privilege', 'name' => 'fk_rp_privilege',   'col' => 'privilege_pk', 'ref' => 'acl_privilege', 'refcol' => 'privilege_pk'],
        ];

        foreach ($fks as $fk) {
            // Only drop if FK exists (idempotent — fresh DB won't have it yet after db-seed)
            $exists = $adapter->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                 AND TABLE_NAME = '{$fk['table']}'
                 AND CONSTRAINT_NAME = '{$fk['name']}'",
                AdapterInterface::QUERY_MODE_EXECUTE
            );

            if ((int) $exists->current()['cnt'] > 0) {
                $drop = new AlterTable($fk['table']);
                $drop->dropConstraint($fk['name']);
                $adapter->query($sql->buildSqlString($drop), AdapterInterface::QUERY_MODE_EXECUTE);
            }

            $add = new AlterTable($fk['table']);
            $add->addConstraint(new ForeignKey(
                $fk['name'], $fk['col'], $fk['ref'], $fk['refcol'], 'CASCADE', 'NO ACTION'
            ));
            $adapter->query($sql->buildSqlString($add), AdapterInterface::QUERY_MODE_EXECUTE);
        }
    }

    public function down(AdapterInterface $adapter): void
    {
        $sql = new Sql($adapter);

        $fks = [
            ['table' => 'acl_privilege',      'name' => 'fk_priv_resource',  'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_rule',            'name' => 'fk_rule_resource',  'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_rule',            'name' => 'fk_rule_privilege', 'col' => 'privilege_pk', 'ref' => 'acl_privilege', 'refcol' => 'privilege_pk'],
            ['table' => 'acl_route_privilege', 'name' => 'fk_rp_resource',    'col' => 'resource_pk',  'ref' => 'acl_resource',  'refcol' => 'resource_pk'],
            ['table' => 'acl_route_privilege', 'name' => 'fk_rp_privilege',   'col' => 'privilege_pk', 'ref' => 'acl_privilege', 'refcol' => 'privilege_pk'],
        ];

        foreach ($fks as $fk) {
            $drop = new AlterTable($fk['table']);
            $drop->dropConstraint($fk['name']);
            $adapter->query($sql->buildSqlString($drop), AdapterInterface::QUERY_MODE_EXECUTE);

            $add = new AlterTable($fk['table']);
            $add->addConstraint(new ForeignKey(
                $fk['name'], $fk['col'], $fk['ref'], $fk['refcol'], 'NO ACTION', 'NO ACTION'
            ));
            $adapter->query($sql->buildSqlString($add), AdapterInterface::QUERY_MODE_EXECUTE);
        }
    }
}
```

### IF NOT EXISTS option — decision pending (deferred until after VS Code update)

**Option A — String post-processing** (simple):
```php
$sqlStr = $sql->buildSqlString($alter);
$sqlStr = str_replace('ADD COLUMN', 'ADD COLUMN IF NOT EXISTS', $sqlStr);
$adapter->query($sqlStr, AdapterInterface::QUERY_MODE_EXECUTE);
```

**Option B — Custom MySQL AlterTable decorator** (clean, reusable):
Extend `PhpDb\Mysql\Sql\Ddl\AlterTableDecorator`, override `processAddColumns()`
to inject `IF NOT EXISTS` into each column spec. Register with the MySQL platform.

---

## 5. FILES TO CREATE/MODIFY

| Action | Path |
|--------|------|
| CREATE | `data/migrations/MigrationInterface.php` |
| CREATE | `data/migrations/Migration023AclResourceSystemColumn.php` |
| CREATE | `bin/migrate` |
| SIMPLIFY | `data/schema/023a_acl_resource_system_col.sql` → single ADD COLUMN IF NOT EXISTS only |
| UPDATE | `composer.json` — add `App\\Migration\\` PSR-4 autoload entry |

### composer.json autoload addition
```json
"App\\Migration\\": "data/migrations/"
```
Then run `composer dump-autoload`.

---

## 6. IDEMPOTENCY REQUIREMENTS

Migration 023 must be idempotent because the live DB already has:
- `system` column: EXISTS ✓ (applied in prior session)
- FK cascades: APPLIED ✓ (fixed manually this session via 10 individual execute() calls)

The migration will be recorded in `schema_migrations` after first run.
Subsequent `bin/migrate` calls will SKIP it (version already tracked).

Fresh DB path (after `db-seed`): 023a adds the column; migration 023 then checks
`information_schema` before each DROP — no error if FK doesn't exist yet.

---

## 7. CODEBASE STATUS (AS OF THIS SESSION END)

### Working ✓
- Lock button rendering — 13 system resources show disabled lock, non-system show trash
- Login HTMX IIFE fix (`const` re-declaration bug solved)
- Pencil button / Bootstrap crash removed from `admin-resources.phtml`
- Double-backtick WHERE clause fix in `AclRepository::deleteResource()`
- `insertPrivilege()` select-then-insert idempotency
- Resource creation end-to-end ✓
- Resource deletion end-to-end ✓ (FKs fixed manually in live DB)
- ON DELETE CASCADE FK chain: `acl_resource` → `acl_privilege` → `acl_rule` / `acl_route_privilege`

### Still Needs Work ✗
- `bin/migrate`: NOT YET CREATED
- `MigrationInterface`: NOT YET CREATED
- `Migration023` class: NOT YET CREATED
- `data/schema/023a`: Still multi-statement, still broken under db-seed
- `composer.json` autoload: Needs `App\Migration` namespace

---

## 8. KEY TECHNICAL FACTS

- **MySQL version**: 8.4.9 — `DROP CONSTRAINT` works as FK alias ✓
- **PHP version**: 8.5.5
- **phpdb**: `Sql::buildSqlString(AlterTable)` → valid SQL string for a single ALTER
- **Execution**: `$adapter->query($sqlString, AdapterInterface::QUERY_MODE_EXECUTE)`
- **DO NOT** chain `dropConstraint()` + `addConstraint()` on same `AlterTable` — ADD renders before DROP
- **DDL rule**: Raw SQL strings ARE acceptable for DDL (phpdb skill only prohibits raw DML)
- **`system` column**: MySQL reserved word — bare `'system'` key in Laminas Sql builder arrays, backtick in raw SQL strings
- **TINYINT comparison**: `(int) $row['system'] === 1` not `=== '1'`
- **`bin/db-seed`**: `QUERY_MODE_EXECUTE` only executes first statement from multi-statement strings — one SQL statement per file required
