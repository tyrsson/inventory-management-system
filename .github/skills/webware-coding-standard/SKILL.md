---
name: "webware-coding-standard"
description: "ALWAYS load before writing or reviewing ANY PHP file in this project. No exceptions. Enforces Webware 1.0 coding standard rules manually when php-cs-fixer cannot run. Skipping this skill produces WET, non-compliant code."
argument-hint: "<file or class being created/reviewed>"
---

> ⚠ **MANDATORY — LOAD BEFORE TOUCHING ANY PHP FILE**
> This skill must be loaded before writing or reviewing any PHP source file. No exceptions.
> Failure to load this skill is the primary cause of coding standard violations in this project.

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Overview

The **Webware 1.0 coding standard** (`@Webware/coding-standard-1.0`) extends `@PER-CS3x0`.
When `php-cs-fixer` cannot run (e.g. due to extension incompatibilities), these rules must
be enforced manually when writing or reviewing code.

---

## File Structure

Every PHP file must open exactly like this (order is mandatory):

```php
<?php

declare(strict_types=1);

namespace Vendor\Package;

use Some\ClassName;
use Another\ClassName;

use function array_map;
use function sprintf;

use const PHP_EOL;
```

Rules:
- `<?php` on line 1, blank line, then `declare(strict_types=1);`
- **One blank line** after `declare`
- **Two blank lines** before `namespace` (`blank_lines_before_namespace: min 2, max 2`)
- **One blank line** after `namespace` (`blank_line_after_namespace`)
- Import groups in order: **class → function → const**, each group alphabetically sorted
- **One blank line between import groups** (`blank_line_between_import_groups`)
- **One blank line after all imports** before the class/function (`single_line_after_imports`)
- No leading slash on imports (`no_leading_import_slash`)
- No unused imports (`no_unused_imports`)
- One import per statement — never `use A, B;` (`single_import_per_statement`)
- No import aliases unless required (`no_unneeded_import_alias`)
- `global_namespace_import`: always `use function` and `use const` for built-ins — never call unimported global functions or constants

---

## Import Ordering — Critical

`ordered_imports` with `imports_order: [class, function, const]`:

```php
// ✅ Correct
use DateTimeImmutable;
use RuntimeException;

use function array_merge;
use function sprintf;

use const PHP_EOL;

// ❌ Wrong — mixed order, grouped import, leading slash
use \DateTimeImmutable, \RuntimeException;
use function sprintf;
use DateTimeInterface;
```

---

## Class Structure

### Class Definition
- `class_definition`: single line, single item single line, multi-line extends each on own line
- No space before parenthesis on anonymous classes

### Class Element Order (`ordered_class_elements`)
Elements must appear in this sequence:
1. `use_trait`
2. `case` (enums)
3. `constant_public` → `constant_protected` → `constant_private`
4. `property_public` → `property_protected` → `property_private`
5. `__construct`
6. `__destruct`
7. Magic methods
8. PHPUnit methods
9. `method_public` → `method_protected` → `method_private`

### Separation Between Elements (`class_attributes_separation`)
- Between **methods**: one blank line
- Between **properties**: one blank line
- Between **constants**: one blank line
- Between **trait imports**: no blank line

### Visibility
Always explicit on properties, methods, and constants (`visibility_required`).

### Readonly / Final
- Use `final` on classes where inheritance is not designed for
- Use `readonly` on constructor-promoted properties where appropriate
- `final readonly class` is the default for value objects and entities

### `#[Override]` Attribute
- Add `#[Override]` to every method that implements an interface method or overrides a parent class method
- `attribute_empty_parentheses: use_parentheses = false` — write `#[Override]` not `#[Override()]`
- The attribute is a class (built-in since PHP 8.3) — import it with `use Override;` in the class import block
- Place `#[Override]` on the line immediately before the method's PHPDoc (if any) or before the `public`/`protected` keyword

```php
// ✅ Correct
use Override;

// ...

    /** @return string[] */
    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

// ❌ Wrong — missing use, parentheses present
    #[\Override()]
    public function getRoles(): array { ... }
```

---

## Operators & Spacing

- `binary_operator_spaces`: align operators with minimal single space; `=>` aligns by scope
- `concat_space`: one space either side of `.`
- `not_operator_with_successor_space`: `! $value` (space after `!`)
- `object_operator_without_whitespace`: no space around `->`
- `no_space_around_double_colon`: `Foo::BAR` not `Foo :: BAR`
- `ternary_operator_spaces`: spaces around `?` and `:`
- `ternary_to_null_coalescing`: prefer `??` over `?: null`
- `assign_null_coalescing_to_coalesce_equal`: prefer `??=`
- `operator_linebreak`: for multi-line expressions, operator goes at the **beginning** of the next line
- `logical_operators`: use `&&` / `||` not `and` / `or`
- `standardize_not_equals`: use `!=` not `<>`

---

## Strings

- Single quotes by default (`single_quote`)
- No embedded variables in double-quoted strings — use `explicit_string_variable` (wrap in `{}`)
- Prefer nowdoc over heredoc where there is no interpolation (`heredoc_to_nowdoc`)
- No useless string concatenation (`no_useless_concat_operator`)
- No `sprintf` where not needed (`no_useless_sprintf`)

---

## Arrays

- Short syntax `[]` always (`array_syntax: short`)
- **Multiline arrays: one value or one `key => value` pair per line** — never multiple entries on one line
- Trailing comma on multiline arrays (`trailing_comma_in_multiline: arrays`)
- No trailing comma in single-line arrays (`no_trailing_comma_in_singleline`)
- One space after commas (`whitespace_after_comma_in_array`)
- No space before commas (`no_whitespace_before_comma_in_array`)
- Trim spaces inside brackets (`trim_array_spaces`)
- Align `=>` by scope (`binary_operator_spaces`)

```php
// ✅ Correct — one entry per line
$middlewareFactory->prepare(
    [
        AuthenticationMiddleware::class,
        DashboardHandler::class,
    ]
);

// ❌ Wrong — multiple entries on one line
$middlewareFactory->prepare(
    [AuthenticationMiddleware::class, DashboardHandler::class]
);
```

---

## Control Structures

- Always use braces (`control_structure_braces`)
- `else`/`elseif`/`catch`/`finally` on **same line** as closing brace (`control_structure_continuation_position: same_line`)
- Use `elseif` not `else if` (`elseif`)
- No superfluous `elseif` / `else` when previous branch returns (`no_superfluous_elseif`, `no_useless_else`)
- `switch continue` → `switch break` (`switch_continue_to_break`)
- Trailing comma in multiline `match` and multiline parameter lists

---

## Functions & Methods

- No space between function name and `(` (`no_spaces_after_function_name`)
- Return type: no space before `:` (`return_type_declaration: none`)
- Multiline argument lists: fully multiline — each arg on its own line (`method_argument_space: ensure_fully_multiline`)
- Trailing comma on multiline parameter lists (`trailing_comma_in_multiline: parameters`)
- No unused `use` in closures (`lambda_not_used_import`)
- Single-line throw expressions (`single_line_throw`)

---

## PHPDoc

- `phpdoc_align: left` — tags left-aligned, not column-aligned
- `phpdoc_line_span`: `const` and `property` → single-line doc; `method` → null (either)
- Remove: `@api`, `@author`, `@category`, `@copyright`, `@created`, `@license`, `@package`, `@subpackage`, `@version` (`general_phpdoc_annotation_remove`)
- No empty PHPDoc blocks (`no_empty_phpdoc`)
- No superfluous `@param`/`@return` tags when types are declared natively (`no_superfluous_phpdoc_tags`)
- PHPDoc tag order: `@internal`, `@deprecated`, `@link`, `@see`, `@uses`, `@param`, `@return`, `@throws`
- `@param` tags ordered to match function signature (`phpdoc_param_order`)
- Type order: alpha-sorted, `null` always last (`phpdoc_types_order`)
- Use scalar type aliases: `bool` not `boolean`, `int` not `integer`, `float` not `double` (`phpdoc_scalar`)
- `@var` without variable name on property docblocks (`phpdoc_var_without_name`)
- No `@return void` or `@return self` when redundant (`phpdoc_no_empty_return`)
- No inline `{@inheritdoc}` — use `@inheritDoc` annotation (`phpdoc_tag_casing`)

---

## Whitespace & Blank Lines

- 4-space indentation (from PER-CS)
- No trailing whitespace on lines or in blank lines
- Single blank line at EOF
- Blank line **before** these statements (when not at block start):
  `break`, `case`, `continue`, `declare`, `default`, `exit`, `goto`,
  `include`, `include_once`, `phpdoc`, `require`, `require_once`,
  `return`, `switch`, `throw`, `try`, `yield`, `yield_from`
- No extra blank lines after `use`, `return`, `throw`, `case`, etc.
  (`no_extra_blank_lines` tokens: `attribute`, `break`, `case`, `continue`,
  `curly_brace_block`, `default`, `extra`, `parenthesis_brace_block`, `return`,
  `square_brace_block`, `switch`, `throw`, `use`)
- Method chaining: each chained call on its own line, indented (`method_chaining_indentation`)

---

## Strict / Type Safety

- `declare(strict_types=1)` in every file (`declare_strict_types`)
- `strict_comparison`: always `===` / `!==`
- `strict_param`: always pass `$strict = true` to functions that accept it (e.g. `in_array`)
- `compact_nullable_type_declaration`: `?Foo` not `? Foo`
- `type_declaration_spaces`: no space around `|` / `&` in union/intersection types (`types_spaces: none`)

---

## Casing

- Keywords lowercase (`lowercase_keywords`)
- `true`, `false`, `null` lowercase (`constant_case: lower`)
- `self`, `static`, `parent` lowercase (`lowercase_static_reference`)
- Native function names lowercase (`native_function_casing`)
- Native type declarations lowercase (`native_type_declaration_casing`)
- Magic constants in canonical casing (`magic_constant_casing`)
- Magic methods in canonical casing (`magic_method_casing`)

---

## Copyright Header (`@Webware/copyright-header`)

When a file belongs to a package with the copyright ruleset enabled, a PHPDoc
header block must appear **after** `declare(strict_types=1)`, separated by a
blank line on both sides:

```php
<?php

declare(strict_types=1);

/**
 * This file is part of the Vendor Package Name package.
 *
 * Copyright (c) 2026 Author Name <author@example.com>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vendor\Package;
```

---

## Quick Checklist — Before Submitting Any PHP File

- [ ] `declare(strict_types=1)` present
- [ ] Imports: class → function → const, alphabetical within each group, blank line between groups
- [ ] All built-in functions imported with `use function`
- [ ] All built-in constants imported with `use const`
- [ ] No unused imports
- [ ] `#[Override]` on all interface/parent method implementations; `use Override;` imported
- [ ] Class elements in correct order (traits → constants → properties → construct → magic → methods)
- [ ] Explicit visibility on all properties, methods, constants
- [ ] Single quotes for strings (unless interpolation required)
- [ ] `===` / `!==` for comparisons
- [ ] `strict_param` — `in_array(..., true)` etc.
- [ ] Trailing comma on all multiline arrays, parameter lists, and `match` arms
- [ ] No trailing comma in single-line arrays/calls
- [ ] PHPDoc only where native types are insufficient; no superfluous tags
- [ ] Blank line before `return`, `throw`, `try` (when not the first statement in a block)
- [ ] One blank line between methods/properties; no blank line between trait imports
- [ ] PHPUnit test classes have `#[CoversClass(...)]` (one per class under test) — see PHPUnit Coverage Attributes section

---

## PHPUnit Coverage Attributes — Mandatory (PHPUnit 11+)

PHPUnit 11 and later require **explicit coverage targets** on every test class. Missing attributes produce "risky test" warnings and will eventually become errors.

### Rules
- Every test class **must** have at least one `#[CoversClass(Foo::class)]` attribute
- Add one `#[CoversClass]` per class under test — multiple are allowed
- Place all `#[CoversClass]` attributes directly above the `class` declaration, after the docblock
- Import `PHPUnit\Framework\Attributes\CoversClass` in the class imports
- For tests that cover free functions, use `#[CoversFunction('functionName')]` instead

---

## PHPUnit Mocks vs Stubs — Mandatory Rule (PHPUnit 12+)

**Using `with*()` without `expects()` is deprecated and will be an error in PHPUnit 14.**

Rule: **if a test double does not set expectations, use `createStub()`. If it sets expectations (call count, arguments), use `createMock()` with `expects()`.**

```php
// ✅ Correct — expectation set, use createMock()
$bus = $this->createMock(CommandBusInterface::class);
$bus->expects($this->once())
    ->method('handle')
    ->with($this->isInstanceOf(SaveRoleCommand::class))
    ->willReturn($result);

// ✅ Correct — no expectation, use createStub()
$messenger = $this->createStub(SystemMessengerInterface::class);
$messenger->method('danger')->willReturn(null);

// ❌ Wrong — with() without expects() is deprecated
$acl = $this->createMock(AclInterface::class);
$acl->method('isAllowed')->with($role, $resource, 'create')->willReturn(true);

// ❌ Wrong — createMock() when no expectations are needed
$messenger = $this->createMock(SystemMessengerInterface::class);
```

---

## PHPUnit Coverage Attributes — Mandatory (PHPUnit 11+)

PHPUnit 11 and later require **explicit coverage targets** on every test class. Missing attributes produce "risky test" warnings and will eventually become errors.

### Rules
- Every test class **must** have at least one `#[CoversClass(Foo::class)]` attribute
- Add one `#[CoversClass]` per class under test — multiple are allowed
- Place all `#[CoversClass]` attributes directly above the `class` declaration, after the docblock
- Import `PHPUnit\Framework\Attributes\CoversClass` in the class imports
- For tests that cover free functions, use `#[CoversFunction('functionName')]` instead

```php
// ✅ Correct
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Acl\Assertion\OwnershipAssertion;

#[CoversClass(OwnershipAssertion::class)]
final class OwnershipAssertionTest extends TestCase
{
    #[Test]
    public function returnsFalseWhenRoleIsNotProprietary(): void { ... }
}

// ✅ Multiple classes under test
#[CoversClass(OwnershipAssertion::class)]
#[CoversClass(AssertionAggregate::class)]
final class OwnershipAssertionIntegrationTest extends TestCase { ... }

// ❌ Wrong — missing CoversClass, will be reported as risky
final class OwnershipAssertionTest extends TestCase { ... }
```

---

## Readonly Classes — Prefer Class-Level over Property-Level

When all properties of a class are readonly, use `readonly class` rather than marking each property individually.

```php
// ✅ Correct — class-level readonly
final readonly class SaveRoleCommand implements CommandInterface
{
    public function __construct(
        public string $name,
        public string $description,
    ) {}
}

// ❌ Wrong — per-property readonly when class-level is possible
final class SaveRoleCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
    ) {}
}
```

Fall back to per-property `readonly` only when `readonly class` is prevented:
- The class extends a non-readonly parent
- One or more properties must remain mutable
- A dependency injection framework (or other tool) requires non-readonly construction

---

## Webware Component Config Key Convention

All webware component configuration is stored in the merged config array under the **component's primary interface class** as the top-level key — never a plain string like `'webware-acl'` or `'webware-navigation'`.

```php
// ✅ Correct — factory reads from interface class key
$config = $container->get('config');
$acl    = $config[AclInterface::class] ?? [];
$nav    = $config[NavigationInterface::class] ?? [];

// ❌ Wrong — string keys
$acl = $config['webware-acl'] ?? [];
$nav = $config['webware-navigation'] ?? [];
```

Rules:
- The config key is always the **FQCN of the component's primary interface** (e.g. `AclInterface::class`, `NavigationInterface::class`)
- Defaults are declared in each module's `ConfigProvider` (e.g. `getAclConfig()`, `getNavigationConfig()`)
- Application overrides are placed under the same interface key in `config/autoload/*.local.php`
- Factories must import the interface and use `$config[TheInterface::class]` — never a hard-coded string
