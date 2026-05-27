---
name: "phpdb"
description: "ALWAYS load when writing or reviewing any code that uses PhpDb: adapters, TableGateway, Sql query builder (Select/Insert/Update/Delete), the Profiler, or wiring the ProfilingDelegator for Tracy. Covers all DML patterns, last-insert-id retrieval, and the Tracy profiler integration via webware/traccio."
argument-hint: "<what you are working on — e.g. 'new repository', 'INSERT query', 'profiler setup'>"
---

> ⚠ **MANDATORY — LOAD BEFORE WRITING ANY PHPDB / REPOSITORY CODE**
> This skill must be loaded before writing or reviewing any repository, TableGateway, or Sql query builder code. No exceptions.
> Failure to load this skill is the primary cause of raw SQL and query builder violations in this project.

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Core Rule — Always Use the `PhpDb\Sql\*` API for DML

Raw SQL strings passed to `$adapter->query()` are **forbidden** for SELECT / INSERT / UPDATE / DELETE.
Raw strings are only acceptable for DDL (`CREATE TABLE`, `ALTER TABLE`, etc.).

---

## When to Use `Sql` Directly vs `TableGateway`

Use `TableGateway` when you need its higher-level features: result set prototypes, row gateway,
or feature plugins. For simple repositories that just need a query builder, construct `Sql` directly —
it avoids unnecessary overhead:

```php
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sql\Sql;

// Direct — preferred for simple query-only classes
$sql = new Sql($adapter, 'log');
$insert = $sql->insert()->values($data);
$sql->prepareStatementForSqlObject($insert)->execute();
```

`TableGateway` internally does exactly this — calling `$gateway->getSql()` just returns the
bound `Sql` instance. Skip the gateway when you don't need anything else it provides.

---

## Adapter and TableGateway

Inject `PhpDb\Adapter\AdapterInterface`. Every repository owns a `TableGateway` instance
bound at construction time:

```php
use PhpDb\Adapter\AdapterInterface;
use PhpDb\TableGateway\TableGateway;

final class UserRepository implements UserRepositoryInterface
{
    private readonly TableGateway $gateway;

    public function __construct(AdapterInterface $adapter)
    {
        $this->gateway = new TableGateway('user', $adapter);
    }
}
```

`$this->gateway->getSql()` returns a `Sql` pre-bound to `'user'` —
`$sql->select()` is already scoped to that table.

---

## Executing Queries

Always prepare and execute via `prepareStatementForSqlObject()`:

```php
$sql    = $this->gateway->getSql();
$select = $sql->select()
    ->join('role', 'role.id = user.role_id', ['role_name' => 'role_id'])
    ->where(['user.email' => $email])
    ->limit(1);

$row = $sql->prepareStatementForSqlObject($select)->execute()->current();
```

---

## INSERT

```php
$sql    = $this->gateway->getSql();
$insert = $sql->insert()->values($data);

$sql->prepareStatementForSqlObject($insert)->execute();

// getLastGeneratedValue() is on DriverInterface directly — no ->getConnection() needed.
// When injecting AdapterInterface directly (preferred over TableGateway for simple repos):
$id = (int) $this->adapter->getDriver()->getLastGeneratedValue();
// When a TableGateway is available, use its own convenience method instead:
$id = (int) $this->gateway->getLastInsertValue();
```

- Pass the full `$data` array to `values()` — column names are the array keys.
- Retrieve the last insert ID from the driver connection **after** execute.
- Do **not** put the auto-increment `id` key inside `$data`.

---

## UPDATE

```php
$sql    = $this->gateway->getSql();
$update = $sql->update()->set($data)->where(['id' => $id]);

$sql->prepareStatementForSqlObject($update)->execute();
```

Do **not** put `id` inside `$data` — pass it as a separate `where()` predicate.

---

## DELETE

```php
$sql    = $this->gateway->getSql();
$delete = $sql->delete()->where(['id' => $id]);

$sql->prepareStatementForSqlObject($delete)->execute();
```

---

## Profiler

`PhpDb\Adapter\Profiler\Profiler` records every query as a `ProfileShape`:

```php
/** @phpstan-type ProfileShape array{
 *     sql: string,
 *     parameters: ParameterContainer|null,
 *     start: float,
 *     end: float|null,
 *     elapse: float|null,
 * }
 */
```

The profiler is attached to the adapter via `$adapter->setProfiler(new Profiler())`.
Retrieve all recorded profiles with `$adapter->getProfiler()->getProfiles()`.

---

## Tracy Integration (webware/traccio ProfilingDelegator)

`Webware\Traccio\PhpDb\ProfilingDelegator` wraps `AdapterInterface` at DI resolution time
and attaches a fresh `Profiler` instance. This makes the SQL profiler tab appear in Tracy
automatically — no other code changes are needed.

Register it in `config/autoload/dependencies.global.php`:

```php
use PhpDb\Adapter\AdapterInterface;
use Webware\Traccio\PhpDb\ProfilingDelegator;

return [
    'dependencies' => [
        'delegators' => [
            AdapterInterface::class => [
                ProfilingDelegator::class,
            ],
        ],
    ],
];
```

- The delegator only needs to be registered once in the app config — **not** inside any module `ConfigProvider`.
- The `SqlProfilerPanel` in traccio reads `$adapter->getProfiler()` and renders the tab automatically when Tracy is enabled (development mode ON).
- When development mode is OFF (Tracy disabled), the profiler still runs but its data is simply never read — negligible overhead.

---

## mysql.local.php adapter config shape

```php
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Mysql\Pdo\Driver;

return [
    AdapterInterface::class => [
        'driver'     => Driver::class,
        'connection' => [
            'dbname'   => 'your_db',
            'host'     => 'mysql',
            'port'     => '3306',
            'username' => 'user',
            'password' => 'pass',
        ],
    ],
];
```
