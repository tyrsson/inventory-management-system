---
name: "laminas-view-helpers"
description: "Load when using any Laminas view helper in templates: headTitle, headLink, headScript, headMeta, inlineScript, basePath, url, asset, escapeHtml. Covers ordering rules, prepend vs append, title separator pattern, and the rendering-order trap."
argument-hint: "<template or helper usage you are working on>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## Rendering Order — The Critical Rule

**The layout template renders LAST.** Page templates render first, then body, then layout. This means:

- When the layout calls `<?= $this->headLink() ?>` it flushes whatever is in the queue at that moment — including everything added by page templates that ran before it.
- `prepend*` adds to the **front** of the internal stack. `append*` adds to the **back**.
- The layout uses `prependStylesheet` / `prependFile` for its own base resources so that **page-level additions** (which use `append*`) always end up **after** the layout's base resources in the final output.

```
Layout calls prepend (base Bootstrap CSS) → stack: [bootstrap.css]
Page calls    append  (page-specific CSS) → stack: [bootstrap.css, page.css]
Layout renders <?= $this->headLink() ?>   → output: bootstrap.css then page.css  ✅

If layout had used append:
Layout calls append (bootstrap.css)       → stack: [bootstrap.css]
Page calls  append (page.css)             → stack: [bootstrap.css, page.css]
Same result by accident — BUT:

Layout calls append (bootstrap.css)       → stack: [bootstrap.css]
Page calls  prepend (page.css)            → stack: [page.css, bootstrap.css]
Layout renders                            → output: page.css before bootstrap.css ✗
```

**Rule of thumb**: Layout always `prepend*` its foundational resources. Page templates always `append*` additions. This guarantees correct order regardless of which runs first.

---

## headTitle — Separator Pattern

The layout sets the base title **and** the separator once:

```php
// layout/default.phtml
<?= $this->headTitle('My Application')->setSeparator(' | ') ?>
```

Page templates push their segment — the separator is applied automatically:

```php
// any page template
$this->headTitle('Dashboard');
// renders: "Dashboard | My Application"

$this->headTitle('Edit Profile');
// renders: "Edit Profile | My Application"
```

Default append order puts the page segment first (`PREPEND` is the default order for `headTitle` in Laminas — it prepends to the stack, so later calls appear before earlier ones in the rendered title). **Do not call `setSeparator` in page templates** — it is set once in the layout.

---

## headLink — Stylesheets

```php
// Layout — foundational, use prepend so pages can append after
$this->headLink()
    ->prependStylesheet($this->basePath('assets/css/custom.css'))
    ->prependStylesheet('https://cdn.jsdelivr.net/.../bootstrap.min.css');

// Page template — additions, use append
$this->headLink()->appendStylesheet($this->basePath('assets/css/page-specific.css'));
```

For non-stylesheet link tags (favicon, preconnect) use the array form:

```php
$this->headLink(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => $this->basePath('favicon.svg')]);
```

---

## headScript / inlineScript

- `headScript()` — scripts in `<head>` (e.g. HTMX, which must load before `<body>`)
- `inlineScript()` — scripts before `</body>` (Bootstrap JS, app.js, page scripts)

```php
// Layout — foundational scripts, use prepend
$this->headScript()->appendFile('https://cdn.jsdelivr.net/.../htmx.min.js', [...]);

$this->inlineScript()
    ->prependFile('https://cdn.jsdelivr.net/.../bootstrap.bundle.min.js')
    ->appendFile($this->basePath('assets/js/app.js'));

// Page template — page-scoped inline script
$this->inlineScript()->appendScript(<<<'JS'
    // page-specific JS here
JS);
```

---

## State Reset

Helpers reset their internal state after each full render cycle (`$this->helpers->resetState()`). A flag or resource added for one request **will not** leak into subsequent requests. Safe to call `headTitle()`, `headLink()`, etc. freely from page templates.

---

## Quick Reference

| Helper | Layout usage | Page usage |
|---|---|---|
| `headTitle` | `headTitle('App Name')->setSeparator(' \| ')` | `headTitle('Page Title')` |
| `headLink` | `prependStylesheet(...)` | `appendStylesheet(...)` |
| `headScript` | `appendFile(...)` for head scripts | `appendFile(...)` or `appendScript(...)` |
| `inlineScript` | `prependFile(bootstrap)`, `appendFile(app.js)` | `appendScript(...)` |
| `headMeta` | `setCharSet`, `appendName` | `appendName` for page-specific meta |
