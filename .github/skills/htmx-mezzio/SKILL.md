---
name: "htmx-mezzio"
description: "ALWAYS load this skill when creating or modifying any handler, page, partial, template, or HTMX interaction. The custom 3-layer rendering stack (layout / body / page) is non-obvious and violations silently break HTMX navigation, JS deduplication, and the toast system."
argument-hint: "<what to create — e.g. 'new page handler', 'product detail handler', 'HTMX partial for form submission', 'navigation link'>"
---

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

## HTMX Swap Target — Critical Architecture Note

HTMX **does not swap `<body>`**. The swap target is a `<main>` element inside the body template (`Htmx/templates/body/default.phtml`).

The rendering stack has three layers:
1. **Page template** (`{namespace}::{template-name}`) — handler content only, renders into `$this->content`
2. **Body template** (`Htmx/templates/body/default.phtml`) — application chrome (nav, sidebar, `<main><?= $this->content ?></main>`, footer). This is what HTMX swaps in on boosted navigation.
3. **Layout template** (`{app}/templates/layout/default.phtml`) — the full HTML document with `<head>`, all CDN assets, JS, and persistent UI containers (e.g. toast). Rendered **only once** on the initial full-page load.

On HTMX requests, `DetectAjaxRequestMiddleware` sets `layout = false`, skipping Layer 3. Only the body partial (Layer 2) is returned and swapped into `<main>`.

**Consequences for JS and UI elements:**
- The layout `<script>` block and all its event listeners (`htmx.on`, `document.addEventListener`) execute **once** and persist for the session — no duplication.
- The toast container (`#systemMessage`) lives in the layout outside `<main>` and is never swapped out. This element is provided by the `axleus/axleus-message` component, which the Htmx module has a soft dependency on.
- Do not add JS to the body template — it will re-execute on every navigation and cause duplicate listeners.

## Bootstrap Component Reinitialisation After HTMX Swap — Critical

Every HTMX navigation replaces the body template (sidebar, nav, all chrome) inside `<main>`. Bootstrap attaches component instances (Collapse, Offcanvas, Modal, Tooltip, Popover) to specific DOM nodes. When those nodes are replaced by a swap, the old instances are orphaned and the new nodes have none — resulting in components that open but will not close, or do not respond at all.

**Fix: call `getOrCreateInstance` in an `htmx:afterSettle` listener in `public/assets/js/app.js`.**

```js
// Re-wire Bootstrap Collapse after every HTMX swap
function initBootstrapComponents() {
  document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (el) {
    var targetSelector = el.getAttribute('data-bs-target') || el.getAttribute('href');
    if (targetSelector) {
      var targetEl = document.querySelector(targetSelector);
      if (targetEl) {
        bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false });
      }
    }
  });
}
document.addEventListener('htmx:afterSettle', initBootstrapComponents);
```

Apply the same pattern for **every Bootstrap interactive component** used in the body template:

| Component | Re-init call |
|---|---|
| Collapse | `bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false })` |
| Offcanvas | `bootstrap.Offcanvas.getOrCreateInstance(el)` |
| Modal | `bootstrap.Modal.getOrCreateInstance(el)` |
| Tooltip | `bootstrap.Tooltip.getOrCreateInstance(el)` |
| Popover | `bootstrap.Popover.getOrCreateInstance(el)` |

**Rules:**
- All `initBootstrap*` functions live in `public/assets/js/app.js`, inside the `htmx:afterSettle` listener — never in templates.
- Any click listeners bound to body-template DOM nodes (e.g. `.ims-store-item`) must also be re-bound in `htmx:afterSettle` — and called once immediately on page load.
- Use `htmx:afterSettle`, not `htmx:afterSwap` — settle fires after CSS transitions complete, guaranteeing the DOM is stable.

## Variable Propagation Into Body Template

Variables set in the page `ViewModel` (or passed via `render()` params) are merged up into the body model via `LaminasRenderer::renderRecursively()` / `mergeViewModel()`. This means any variable passed from the handler is available in `body/default.phtml`.

Use this to conditionally suppress chrome on pages that don't need it (e.g. login):

```php
// In the handler:
return new HtmlResponse($this->template->render('module::page', ['sidebar' => false]));
```

```php
// In body/default.phtml:
<?php if ($this->sidebar !== false): ?>
    <!-- sidebar HTML -->
<?php endif; ?>
```

**Never assume the body won't render.** If sidebar/topbar appears unexpectedly, the fix belongs in `body/default.phtml` with a conditional — not in the handler or a separate body template.

## Layout Disable

Pass `'layout' => false` in render params to skip Layer 3 (the full HTML document wrapper). `DetectAjaxRequestMiddleware` does this automatically for all HTMX requests. Helpers (`headLink`, `headScript`, `inlineScript`, etc.) reset their state after each render — so a flag set for one request will not bleed into the next.

## No Inline Styles — Mandatory

**Never use `style="..."` attributes in templates.** All visual styling must live in `public/assets/css/custom.css` as named BEM-style `.ims-*` classes. When a Bootstrap utility class does not exist for the exact value needed, add a new `.ims-*` class to `custom.css` and apply it.

```html
<!-- WRONG -->
<div style="font-size:.875rem; opacity:.8;">...</div>

<!-- RIGHT — add .ims-login-brand-tagline to custom.css, use the class here -->
<div class="ims-login-brand-tagline">...</div>
```

## Laminas View Helpers — Mandatory

**Always use Laminas view helpers instead of hardcoded HTML tags for any resource, URL, or asset reference.** Never write raw `<link>`, `<script>`, `<img>`, or `<a href="">` tags when a helper is available.

| Instead of | Use |
|---|---|
| `<link rel="stylesheet" href="/assets/css/custom.css">` | `$this->headLink()->prependStylesheet($this->basePath('assets/css/custom.css'))` |
| `<script src="/assets/js/app.js">` | `$this->headScript()->appendFile($this->basePath('assets/js/app.js'))` |
| `<a href="/login">` | `<a href="<?= $this->url('route.name') ?>">` |
| `<img src="/assets/img/logo.png">` | `<img src="<?= $this->basePath('assets/img/logo.png') ?>">` |
| `<form action="/resource/create">` | `<form action="<?= $this->url('resource.create') ?>">` |

Key helpers available in every template:
- `$this->basePath('path')` — resolves a path relative to the application base URL
- `$this->url('route-name', ['param' => 'value'])` — generates a URL from a named route
- `$this->asset('file.js')` — resolves a versioned/hashed asset path (uses manifest)
- `$this->headLink()` — queues `<link>` tags (stylesheets, favicons) for the `<head>`
- `$this->headScript()` — queues `<script src="">` tags for the `<head>`
- `$this->inlineScript()` — queues `<script>` blocks/files before `</body>`
- `$this->headMeta()` — queues `<meta>` tags
- `$this->headTitle()` — sets the `<title>` tag
- `$this->escapeHtml($value)` — always escape untrusted output
- `$this->escapeHtmlAttr($value)` — escape values placed inside HTML attributes

## Toast / SystemMessenger Pattern — Mandatory

**Never use `HX-Trigger` headers or custom JS events to show toasts.** The only correct pattern is `SystemMessengerInterface` → `ImsMessenger` view helper → body template pending elements → `showPendingToasts()` in JS.

### How it works

- `#systemMessage` container lives in the **layout** (Layer 3) — rendered once, never swapped out.
- `.ims-pending-toast` elements are rendered by the `ImsMessenger` view helper inside the **body template** (Layer 2) — present on every response, full-page or HTMX boosted.
- `showPendingToasts()` in `app.js` reads `.ims-pending-toast` elements from the swapped body and fires them into `#systemMessage`.
- This works on both initial page loads and HTMX boosted navigations because the body always re-renders.

### PHP side — from processing middleware

```php
// Flash for the CURRENT response (same render cycle — no redirect needed):
$messenger = $request->getAttribute(SystemMessengerInterface::class);
$messenger->danger('Your message here.', hops: 0, now: true);

// Then pass to the handler to render the page with the toast embedded:
return $handler->handle($request);
```

```php
// Flash for the NEXT response (after a redirect):
$messenger->danger('Your message here.', hops: 1, now: false);
return new RedirectResponse($path);
```

### Hop rules

| Scenario | `now` | `hops` |
|---|---|---|
| Same response (no redirect) | `true` | `0` |
| After one redirect | `false` | `1` |
| After two redirects | `false` | `2` |

### Severity methods

```php
$messenger->danger('...',  hops: 0, now: true);   // red   — errors, access denied
$messenger->warning('...', hops: 0, now: true);   // amber — soft warnings
$messenger->success('...', hops: 0, now: true);   // green — completed actions
$messenger->info('...',    hops: 0, now: true);   // blue  — neutral information
```

**Never** put toast logic in a handler — handlers only render responses. Toast calls belong in processing middleware before `$handler->handle($request)`.
