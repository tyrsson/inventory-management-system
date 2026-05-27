# webware-js — JavaScript Client Conventions for Webware Components

> ⚠ **SKILL INTEGRITY — NEVER REMOVE OR SHORTEN**
> Content in this file may only be **added to or updated**. Removing or shortening existing sections is not permitted without explicit user approval. If you are adding new knowledge, append it as a new section.

Load this skill when writing or reviewing any `.phtml` template in a webware
component that includes page-specific JavaScript, or when hooking into HTMX or
Bootstrap events. Host-application-specific rules (asset paths, CSS naming
conventions, toast globals, etc.) are in the `ims-application` skill.

---

## Adding Page-Specific JavaScript

### Option A — `appendScript` with heredoc (preferred for multi-line)

```php
<?php
$this->inlineScript()->appendScript(<<<'JS'
(function () {
    'use strict';
    // ... your code ...
})();
JS);
?>
```

### Option B — `captureStart / captureEnd` (for scripts mixed with PHP)

```php
<?php $this->inlineScript()->captureStart(); ?>
(function () {
    'use strict';
    const val = <?= json_encode($this->somePhpVar) ?>;
})();
<?php $this->inlineScript()->captureEnd(); ?>
```

### Rules

- **Always wrap in an IIFE** — `(function () { 'use strict'; ... })();` — to
  avoid leaking variables into global scope.
- **Never use `<script>` tags directly in templates.** Always go through the
  `inlineScript()` helper so scripts are deduplicated and rendered once at the
  bottom of `<body>`.
- Page scripts are appended **after** `app.js`, so `bootstrap` and `htmx`
  globals are available.
- Do not use ES modules (`import`/`export`). Webware components do not use a
  bundler. Use `var` or IIFE-scoped `const`/`let`.

---

## Adding Page-Specific CSS

- **No inline `style="..."` attributes.** Use named CSS classes.
- Do not hardcode hex colour values — use the host application's CSS custom
  properties (defined in its theme stylesheet).

---

## HTMX Conventions

### Event Delegation — Survives Every Swap

All persistent event listeners **must** be delegated on `document`, not on
specific elements. HTMX replaces `<main>` on navigation; listeners attached to
specific nodes inside `<main>` are destroyed.

```js
// CORRECT — survives HTMX swap
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ims-my-button');
    if (!btn) return;
    // ...
});

// WRONG — destroyed on next HTMX navigation
document.querySelector('.ims-my-button').addEventListener('click', ...);
```

### Re-initialisation After Swap

If a component must be initialised on first load **and** after every swap:

```js
function initMyComponent() {
    document.querySelectorAll('.ims-my-thing').forEach(function (el) {
        // idempotent setup
    });
}
document.addEventListener('htmx:afterSettle', initMyComponent);
initMyComponent(); // also run on initial page load
```

### Programmatic HTMX Requests

Use `htmx.ajax()` to submit forms or trigger requests imperatively (e.g. from
a modal confirm button):

```js
htmx.ajax('POST', url, {
    target: 'main',
    swap:   'innerHTML',
    values: { key: value },
});
```

### Dynamic `hx-*` Attributes

When setting `hx-*` attributes on elements at runtime, always call
`htmx.process(element)` afterwards so HTMX picks up the new attribute:

```js
btn.setAttribute('hx-delete', '/some/path/' + id);
htmx.process(btn);
```

### Bootstrap + HTMX Race — Use Imperative API

Never rely on Bootstrap's `data-bs-*` data-API attributes for elements that
HTMX may re-render. Use the imperative JS API instead:

```js
// Use getOrCreateInstance — safe to call repeatedly
var instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
instance.show();
```

### Modal Cleanup After Swap

If HTMX swaps `<main>` while a Bootstrap modal is open, the modal backdrop
gets orphaned. `app.js` already handles this globally — do not add cleanup code
in page scripts.

### HTMX Events Commonly Used

| Event | When | Common use |
|---|---|---|
| `htmx:afterSettle` | After swap + animations settle | Re-init delegated components |
| `htmx:xhr:progress` | During file upload XHR | Upload progress bar |
| `htmx:responseError` | Non-2xx response | Error feedback |
| `systemMessage` (custom) | Server-sent HTMX event | Toast from server trigger |

---

## Upload Progress Bar Pattern

For file upload forms, use `htmx:xhr:progress` to drive a Bootstrap animated
striped progress bar.

### Markup

```html
<!-- Hidden until upload starts -->
<div id="upload-progress-wrap" class="d-none mb-3">
    <div class="d-flex justify-content-between small text-secondary mb-1">
        <span>Uploading…</span>
        <span id="upload-progress-pct">0%</span>
    </div>
    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div id="upload-progress-bar"
             class="progress-bar progress-bar-striped progress-bar-animated"
             style="width: 0%"></div>
    </div>
</div>
```

### Page script

```php
<?php $this->inlineScript()->appendScript(<<<'JS'
(function () {
    'use strict';

    var form    = document.getElementById('my-upload-form');
    var wrap    = document.getElementById('upload-progress-wrap');
    var bar     = document.getElementById('upload-progress-bar');
    var pctText = document.getElementById('upload-progress-pct');
    var btn     = document.getElementById('upload-submit-btn');

    if (!form || !wrap || !bar) return;

    form.addEventListener('submit', function () {
        wrap.classList.remove('d-none');
        if (btn) btn.disabled = true;
    });

    document.addEventListener('htmx:xhr:progress', function (e) {
        if (!e.detail.total) return;
        var pct = Math.round(e.detail.loaded / e.detail.total * 100);
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', pct);
        if (pctText) pctText.textContent = pct + '%';

        // Once bytes delivered, switch to indeterminate "processing" state
        if (pct >= 100) {
            bar.classList.add('bg-info');
            if (pctText) pctText.textContent = 'Processing…';
        }
    });
})();
JS); ?>
```

**`htmx:xhr:progress` only fires during the byte-transfer phase.** Once
`pct === 100` the server is processing (parse, DB inserts, etc.). Switching to
`bg-info` indeterminate signals that the server is still working. The
server's redirect/swap resets the page when done.

---

## Bootstrap Component Rules

- **Modals**: Use `show.bs.modal` on the modal element (delegated on `document`)
  to populate fields from `data-*` attributes on the trigger button.
- **Collapse**: Always use `getOrCreateInstance` with `{ toggle: false }` and
  call `.show()` / `.hide()` / `.toggle()` explicitly. Manage `aria-expanded`
  manually — Bootstrap doesn't reliably update it when combined with HTMX.
- **Toasts**: Do not instantiate Bootstrap `Toast` directly in component page
  scripts. Use the host application's toast utility (see `ims-application`
  skill for this project's `systemMessage()` global).

---

## Anti-Patterns

- **No jQuery** — not included. Use vanilla DOM APIs.
- **No ES module syntax** — no bundler, no `import`/`export`.
- **No `setTimeout`/`setInterval` polling** — use HTMX `hx-trigger="every Ns"`
  if polling is needed (rare).
- **No direct `fetch()` calls** — use `htmx.ajax()` so swaps work correctly.
- **No Bootstrap data-API attributes** on elements inside `<main>` for
  components that must survive HTMX swaps — use imperative JS + delegation.
