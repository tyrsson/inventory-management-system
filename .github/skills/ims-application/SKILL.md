# ims-application — Farmers IMS Host Application Conventions

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

Load this skill when writing or reviewing any `.phtml` template, JavaScript, or
CSS that is part of the **Farmers IMS host application** (`src/App/`, or any
component template that integrates with the IMS layout). It documents rules that
apply to this specific application and are **not** webware component conventions.

---

## Stack Versions

| Library | Version | How loaded |
|---|---|---|
| Bootstrap | 5.3.3 | CDN — `bootstrap.bundle.min.js` (includes Popper) |
| HTMX | 2.0.8 | CDN via `headScript()` in layout |
| Bootstrap Icons | latest | CDN via `headLink()` in layout |

All three are loaded by `src/App/templates/layout/default.phtml`.
**Do not load them again in page templates.**

---

## Script Loading Order

```
<head>
  <?= $this->headScript() ?>   ← HTMX CDN only
</head>
<body>
  ...page content...
  <?= $this->inlineScript() ?> ← Bootstrap CDN → app.js → system.messenger.js → page scripts
</body>
```

`inlineScript()` renders in the order items were appended/prepended:

1. `prependFile('bootstrap.bundle.min.js')` — always first  
2. `appendFile('assets/js/app.js')` — shared application behaviour  
3. `appendFile($this->asset('messenger.js'))` — toast rendering (`system.messenger.js`)  
4. **Page `captureStart/End` or `appendScript` blocks** — last (page wins)

`system.messenger.js` is registered under the asset alias `messenger.js` in
`config/autoload/app.global.php` and appended by the layout.

---

## CSS Convention — `.ims-*` Class Prefix

All custom CSS classes in this application use the `.ims-` prefix.

- Add new classes to `public/assets/css/custom.css`.
- Do **not** use inline `style="..."` attributes in any `.phtml` template.
- Use the application's CSS custom properties for colours — do not hardcode hex values.

### Available CSS Custom Properties

Defined in `public/assets/css/custom.css` under `[data-bs-theme="dark"]`:

```css
--bs-primary          /* Steel Blue #4a80b4 */
--bs-primary-rgb

--ims-amber           /* #c49b22 */
--ims-amber-rgb
--ims-amber-subtle
--ims-amber-border

/* Status badge tokens */
--ims-damaged-bg / --ims-damaged-color / --ims-damaged-border
--ims-floor-bg    / --ims-floor-color   / --ims-floor-border
--ims-pqa-bg      / --ims-pqa-color     / --ims-pqa-border
--ims-bargain-bg  / --ims-bargain-color / --ims-bargain-border
--ims-overstock-bg / --ims-overstock-color / --ims-overstock-border
--ims-repairable-bg / --ims-repairable-color / --ims-repairable-border
--ims-nonrep-bg   / --ims-nonrep-color  / --ims-nonrep-border
```

---

## Toast System — `systemMessage(level, msg)`

`system.messenger.js` exposes a global `systemMessage(level, msg)` function that
renders Bootstrap toasts into `#systemMessage` (bottom-right, fixed).

```js
systemMessage('success', 'Manifest imported — 42 items added.');
systemMessage('danger',  'Upload failed — invalid CSV format.');
systemMessage('warning', 'Unknown SKUs were added to the catalogue.');
systemMessage('info',    'Processing…');
```

Levels map to Bootstrap `text-bg-{level}` toast classes. Toasts auto-hide after
4 seconds and remove themselves from the DOM.

**Never instantiate `bootstrap.Toast` directly in page scripts — always use
`systemMessage()`.**

### Server-side toast triggers

From PHP middleware, enqueue messages via `SystemMessengerInterface`:

```php
$messenger?->success('Manifest imported.');
$messenger?->danger('Could not parse the CSV.');
$messenger?->warning('3 unknown SKUs added to catalogue.');
```

`system.messenger.js` picks up `.ims-pending-toast` elements after every
`htmx:afterSettle` and after `DOMContentLoaded`, so server-rendered toasts are
displayed on both full-page loads and HTMX partial swaps.

---

## `data-ims-*` Attribute Naming

Custom `data-*` attributes in this application use the `data-ims-{feature}` prefix:

| Attribute | Used for |
|---|---|
| `data-ims-resource-toggle` | ACL resource accordion |
| `data-ims-stores-toggle` | Sidebar store list collapse |

Add new `data-ims-*` attributes for any new delegated behaviour in `app.js`.

---

## HTMX — Application-Level Rules

- **No `href="#"`** on any element that also uses `hx-*` attributes. `hx-boost`
  intercepts all `<a>` clicks; `href="#"` pushes `#` onto the browser history
  and breaks navigation. Use a real route URL or a `<button>` instead.
- The global `hx-boost="true" hx-target="main" hx-push-url="true"` is set on
  `<body>` in the layout. All `<a>` clicks are automatically boosted — no need
  to add `hx-boost` on individual links.

---

## `app.js` — What Lives There vs. Page Scripts

`app.js` contains **application-wide, persistent behaviour** that must survive
every HTMX swap:

- Resources accordion toggle (`data-ims-resource-toggle`)
- Stores sidebar collapse (`data-ims-stores-toggle`)
- Store switcher active state
- ACL rule creation flow (redundancy warning modal → POST)
- Modal population from `data-*` trigger attributes (`show.bs.modal`)
- Bootstrap modal cleanup after HTMX swaps

**Do not add module-specific behaviour to `app.js`.** Page-specific scripts
belong in the template's `inlineScript()` block.
