# Schema-Driven Filter Architecture

_Authored: 2026-05-31_

---

## Problem Statement

Across all web applications there is a fundamental pain point: a single logical
field must be consistently named and typed across multiple layers — the HTML form
attribute, the request body array key, the filter/validator input name, the
command constructor parameter, and the database column name. In this codebase
that problem manifested as scattered `is_array()` checks, `(int)` casts, and
hardcoded key names duplicated independently in each layer with nothing enforcing
consistency between them.

The goal of this architecture is a strict convention with a single source of
truth, so that no component independently decides what a field is called or what
constraints apply to it.

---

## The Chain

Every form submission travels through the following layers:

```
HTML Form (field name attributes)
        │
        ▼
Request body array  (keys match form field names)
        │
        ▼
Filter class        (PSR-7 boundary — the ONLY place raw body is touched)
        │
        ▼
Command / DTO       (typed value transport)
        │
        ▼
CommandHandler      (entry point to persistence)
        │
        ▼
Repository          (DB column mapping)
        │
        ▼
Database
```

---

## Agreed Filter Convention (interim — before webware-filter ships)

Until the full schema-driven infrastructure exists, every form endpoint gets a
`*Filter` class following this pattern:

```php
final class RuleFilter
{
    public readonly string $roleId;
    // ...
    private bool $valid = false;

    private function __construct(array $body) { /* assign + validate */ }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        return new self((array) $request->getParsedBody());
    }

    public function isValid(): bool { return $this->valid; }

    public function getValues(): array { /* keyed to command param names */ }
}
```

**Hard rules:**
1. `(array)` cast lives **only** in `fromRequest()` — never in middleware
2. `isValid()` is the **only** gate — middleware never inspects individual properties
3. `getValues()` keys **must** match the target command's constructor parameter names exactly
4. No filter logic outside the filter class — ever

Middleware pattern:
```php
$filter = RuleFilter::fromRequest($request);
if (! $filter->isValid()) {
    return $handler->handle($request); // early return, no CommandResult attribute
}
$result = $this->commandBus->handle(new SaveRuleCommand(...$filter->getValues()));
```

---

## Long-Term Vision: Schema-Driven Architecture

### Core Insight

The database schema is the most stable contract in the system. Everything else
— forms, filters, commands, repositories — must honour it anyway. Therefore the
schema should be the single source of truth from which all other artefacts are
derived or validated.

`phpdb`'s `Metadata` layer already exposes everything needed:

| `ColumnObject` method | Derivable rule |
|---|---|
| `getDataType()` | type coercion, base filter, form element type |
| `getIsNullable()` | required vs optional |
| `getCharacterMaximumLength()` | `MaxLength(n)` |
| `getNumericPrecision()` / `getNumericScale()` | decimal constraints |
| `isNumericUnsigned()` | min 0 |
| `getColumnDefault()` | default value fallback |
| `getErrata('permitted_values')` | `InArray([...])` for ENUM/SET columns |

The MySQL metadata source (`phpdb-mysql`) populates `permitted_values` errata by
parsing `INFORMATION_SCHEMA.COLUMN_TYPE` for `ENUM` and `SET` columns, returning
`string[]` of the allowed values. No additional annotation is needed.

### `ColumnMetadataInterface`

Lives in **`webware-core`**. Defines the contract that the orchestrator and both
managers consume:

```php
interface ColumnMetadataInterface
{
    public function getName(): string;
    public function getDataType(): string;
    public function getIsNullable(): bool;
    public function getCharacterMaximumLength(): ?int;
    public function isNumericUnsigned(): ?bool;
    public function getColumnDefault(): mixed;
    public function getErrata(string $name): mixed;
}
```

`phpdb`'s `ColumnObject` already satisfies every method but cannot implement
this interface directly (phpdb must not depend on webware packages). The adapter
pattern resolves this — see `webware-orchestra` below.

### Package Responsibilities

```
webware-core
    Defines: ColumnMetadataInterface
    All other webware packages already depend on this — no new dependency introduced.

phpdb / phpdb-mysql
    Unchanged. ColumnObject remains as-is.
    Metadata\Source (MySQL) populates ColumnObject including errata for ENUM/SET.

webware-filter
    Depends on: webware-core
    Owns: FilterManager, AggregateFilter, InputFilterInterface (mirrors Laminas)
    Consumes: ColumnMetadataInterface
    Knows nothing of: ColumnObject, phpdb, webware-form, webware-orchestra
    FilterManager alias map: dataType string → filter chain configuration
    Example aliases:
        'varchar' → StringTrim + MaxLength(n)
        'enum'    → InArray(permitted_values)
        'json'    → JsonDecode transform
        'int'     → Digits + optional min 0 if unsigned
        'boolean' → Boolean

webware-form  (when laminas-form is compatible with laminas-servicemanager v4)
    Depends on: webware-core
    Owns: FormElementManager
    Consumes: ColumnMetadataInterface
    Knows nothing of: ColumnObject, phpdb, webware-filter, webware-orchestra
    FormElementManager alias map: dataType string → form element class
    Example aliases:
        'varchar' → Text
        'enum'    → Select (options from permitted_values)
        'json'    → Hidden
        'int'     → Number
        'boolean' → Checkbox

webware-orchestra
    Depends on: webware-core, phpdb, webware-filter, (later) webware-form
    Owns: ColumnObjectAdapter (implements ColumnMetadataInterface, wraps ColumnObject)
          AggregateFilterFactory (orchestrator — takes table name, builds filter)
          Column type → alias mapping config
    ConfigProvider: opt-in — wires everything together, nothing breaks if absent
    This is the ONLY package that knows about both phpdb and webware-filter.
```

### Dependency Graph

```
webware-core ◄──── webware-filter
webware-core ◄──── webware-form
webware-core ◄──── webware-orchestra
phpdb        ◄──── webware-orchestra
webware-filter ◄── webware-orchestra
webware-form   ◄── webware-orchestra  (future)

phpdb              (no webware dependencies)
webware-filter     (no phpdb dependency)
webware-form       (no phpdb dependency)
```

### The Adapter

`webware-orchestra\Adapter\ColumnObjectAdapter` is the seam between `phpdb` and
the rest of the ecosystem:

```php
final class ColumnObjectAdapter implements ColumnMetadataInterface
{
    public function __construct(private readonly ColumnObject $column) {}

    public function getName(): string               { return $this->column->getName(); }
    public function getDataType(): string           { return $this->column->getDataType(); }
    public function getIsNullable(): bool           { return $this->column->getIsNullable() ?? false; }
    public function getCharacterMaximumLength(): ?int { return $this->column->getCharacterMaximumLength(); }
    public function isNumericUnsigned(): ?bool      { return $this->column->isNumericUnsigned(); }
    public function getColumnDefault(): mixed       { return $this->column->getColumnDefault(); }
    public function getErrata(string $name): mixed  { return $this->column->getErrata($name); }
}
```

### Orchestrator Flow

```
AggregateFilterFactory::fromTable(string $table, AdapterInterface $adapter)
        │
        ├─ Metadata::getColumns($table)
        │   returns ColumnObject[]
        │
        ├─ foreach ColumnObject → wrap in ColumnObjectAdapter
        │
        ├─ foreach ColumnMetadataInterface:
        │   $dataType = $col->getDataType()           // 'varchar', 'enum', etc.
        │   $filterChain = FilterManager::get($dataType, $col)
        │   configure chain: MaxLength, InArray, Digits etc from ColumnObject methods
        │
        └─ return AggregateFilter (isValid() / getValues())
```

### Laminas Interoperability

`webware-filter` defines its own `InputFilterInterface` mirroring the Laminas
`InputFilterInterface` signatures exactly:
- `setData(iterable $data): void`
- `isValid(): bool`
- `getValues(): array`
- `getMessages(): array`

When `laminas/laminas-inputfilter` ships a release compatible with
`laminas/laminas-servicemanager` v4, `webware-filter` makes its interface extend
the Laminas one (or deprecates in favour of it). No consuming code changes because
the method signatures are identical. The migration is a `composer require` swap,
not a refactor.

### Naming Convention — The Key Alignment Problem

The one piece that cannot be derived from the schema automatically is the mapping
between DB column names (`role_id`) and command constructor parameter names
(`$roleId`). This is provided as a developer-owned array in the filter class or
orchestrator configuration:

```php
['role_id' => 'roleId', 'resource_id' => 'resourceId', 'rule_type' => 'type']
```

By convention, `getValues()` on any filter class returns keys matching the target
command's constructor parameter names exactly, so the command can be constructed
with `new SaveRuleCommand(...$filter->getValues())`.

---

## Current Status

- `ColumnMetadataInterface` — **not yet created** (defined here, to be implemented)
- `webware-filter` package — **not yet created**
- `webware-orchestra` package — **not yet created**
- Interim `*Filter` convention — **agreed, being applied** starting with `RuleFilter`
  in `webware-acl`
- `laminas-form` / `laminas-inputfilter` / `laminas-validator` compatible with
  servicemanager v4 — **not yet released**, architecture designed to accommodate
  them when they land
