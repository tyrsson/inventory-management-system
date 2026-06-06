/**
 * app.js — v2
 * Minimal shared JS. Bootstrap handles: offcanvas sidebar, collapse,
 * modals, toasts, form-switches. Only non-Bootstrap behaviour lives here.
 */

(function () {
  'use strict';

  // ── Stores collapse toggle ────────────────────────────────────────────────
  // Delegated on document so it survives every HTMX body swap.
  // Uses Bootstrap imperatively — bypasses data-api to avoid double-toggle
  // races when htmx:afterSettle fires during a collapse animation.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ims-stores-toggle]');
    if (!btn) return;
    var targetEl = document.getElementById('storesList');
    if (!targetEl) return;
    var instance = bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false });
    instance.toggle();
    // Keep aria-expanded in sync for accessibility and CSS hooks
    targetEl.addEventListener('shown.bs.collapse',  function () { btn.setAttribute('aria-expanded', 'true');  }, { once: true });
    targetEl.addEventListener('hidden.bs.collapse', function () { btn.setAttribute('aria-expanded', 'false'); }, { once: true });
  });

  // ── Store switcher — update active store display text ────────────────────
  // When a store button in the sidebar collapse is clicked, mark it active
  // and update the store label in the topbar / sidebar header.
  function initStoreSwitcher() {
    document.querySelectorAll('.ims-store-item').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.ims-store-item').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
  }

  document.addEventListener('htmx:afterSettle', initStoreSwitcher);
  initStoreSwitcher();

  // ── ACL hierarchy view — create rule buttons ─────────────────────────────
  // Handles .ims-rule-create buttons in the hierarchy table.
  // If the target row has elevated descendants (roles that will silently gain
  // access via chain walk-up) or redundant descendants (already have explicit
  // rules), the redundancy warning modal is shown first.
  // After confirmation (or if no warning needed), a POST is submitted.

  var _pendingRulePost = null; // { url, role_pk, resource_pk, privilege_pk, type }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ims-rule-create');
    if (!btn) return;

    var row          = btn.closest('tr');
    var table        = btn.closest('table');
    if (!row || !table) return;

    var rolePk       = row.dataset.rolePk;
    var roleId       = row.dataset.roleId;
    var actionType   = btn.dataset.actionType; // 'allow' or 'deny'
    var elevated     = JSON.parse(row.dataset.elevated  || '[]');
    var redundant    = JSON.parse(row.dataset.redundant || '[]');
    var createUrl    = table.dataset.createUrl;
    var resourcePk   = table.dataset.resourcePk;
    var privilegePk  = table.dataset.privilegePk;
    var resource     = table.dataset.resource;
    var privilege    = table.dataset.privilege;

    _pendingRulePost = {
      url:          createUrl,
      role_pk:      rolePk,
      resource_pk:  resourcePk,
      privilege_pk: privilegePk,
      type:         actionType,
    };

    // Only show elevation warning when adding allow (deny doesn't elevate access)
    var hasElevation = actionType === 'allow' && elevated.length > 0;
    var hasRedundant = redundant.length > 0;

    if (hasElevation || hasRedundant) {
      // Populate modal
      var typeEl      = document.getElementById('redundancyRuleType');
      var roleEl      = document.getElementById('redundancyTargetRole');
      var resEl       = document.getElementById('redundancyResource');
      var privEl      = document.getElementById('redundancyPrivilege');
      var elevList    = document.getElementById('redundancyElevatedList');
      var elevSection = document.getElementById('redundancyElevationSection');
      var redList     = document.getElementById('redundancyDescendantList');
      var redSection  = document.getElementById('redundancyRedundantSection');

      if (typeEl)  { typeEl.textContent = actionType; typeEl.className = actionType === 'allow' ? 'text-success fw-bold' : 'text-danger fw-bold'; }
      if (roleEl)  roleEl.textContent  = roleId;
      if (resEl)   resEl.textContent   = resource;
      if (privEl)  privEl.textContent  = privilege;

      if (elevList && elevSection) {
        elevList.innerHTML = '';
        if (hasElevation) {
          elevated.forEach(function (r) {
            var badge = document.createElement('span');
            badge.className = 'badge bg-warning-subtle border border-warning-subtle text-warning-emphasis';
            badge.textContent = r;
            elevList.appendChild(badge);
          });
          elevSection.style.display = '';
        } else {
          elevSection.style.display = 'none';
        }
      }

      if (redList && redSection) {
        redList.innerHTML = '';
        if (hasRedundant) {
          redundant.forEach(function (r) {
            var badge = document.createElement('span');
            badge.className = 'badge bg-body-tertiary border text-secondary';
            badge.textContent = r;
            redList.appendChild(badge);
          });
          redSection.style.display = '';
        } else {
          redSection.style.display = 'none';
        }
      }

      var modal = new bootstrap.Modal(document.getElementById('redundancyWarningModal'));
      modal.show();
    } else {
      _submitRulePost(_pendingRulePost);
      _pendingRulePost = null;
    }
  });

  // Redundancy warning confirm — submit the pending POST
  // WARNING: do NOT call _submitRulePost() before the modal is fully hidden.
  // modal.hide() only starts the Bootstrap close animation; HTMX swapping <main>
  // while the animation is still running tears the modal element out of the DOM
  // before Bootstrap can remove the backdrop, leaving the page frozen with a
  // CPU-spiking event loop. Always wait for hidden.bs.modal before firing the request.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#redundancyConfirmBtn');
    if (!btn || !_pendingRulePost) return;
    var pending = _pendingRulePost;
    _pendingRulePost = null;
    var modalEl = document.getElementById('redundancyWarningModal');
    var modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) {
      modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        _submitRulePost(pending);
      }, { once: true });
      modal.hide();
    } else {
      _submitRulePost(pending);
    }
  });

  function _submitRulePost(data) {
    // Submit via HTMX programmatic request so hx-boost and swap work correctly.
    // push:false prevents the global hx-push-url="true" on <body> from pushing
    // this action URL into browser history — it's a write operation, not navigation.
    // NOTE: the correct htmx.ajax() option is `push`, NOT `pushUrl` — `pushUrl` is
    // silently ignored, causing the URL to be pushed despite the intent.
    htmx.ajax('POST', data.url, {
      target: 'main',
      swap:   'innerHTML',
      push:   false,
      values: {
        role_pk:      data.role_pk,
        resource_pk:  data.resource_pk,
        privilege_pk: data.privilege_pk,
        type:         data.type,
      },
    });
  }


  // Populate edit/delete modals with data-* attributes from the trigger button.
  // Uses event delegation on the document so it works after HTMX swaps.
  document.addEventListener('show.bs.modal', function (e) {
    var trigger = e.relatedTarget;
    if (!trigger) return;

    // Route-map edit modal
    if (e.target.id === 'editMappingModal') {
      var route     = trigger.dataset.route     || '';
      var resource  = trigger.dataset.resource  || '';
      var privilege = trigger.dataset.privilege || '';
      var routeEl   = document.getElementById('edit_route_name');
      var resEl     = document.getElementById('edit_resource_id');
      var privEl    = document.getElementById('edit_privilege_id');
      if (routeEl) routeEl.value = route;
      if (resEl)   Array.from(resEl.options).forEach(function (o) { o.selected = o.value === resource; });
      if (privEl)  Array.from(privEl.options).forEach(function (o) { o.selected = o.value === privilege; });
    }

    // Route-map delete confirm modal
    if (e.target.id === 'deleteMappingModal') {
      var route = trigger.dataset.route || '';
      var nameEl     = document.getElementById('deleteRouteName');
      var confirmBtn = document.getElementById('deleteMappingConfirmBtn');
      if (nameEl)     nameEl.textContent = route;
      if (confirmBtn) {
        confirmBtn.setAttribute('hx-delete', '/admin/access/routes/' + encodeURIComponent(route));
        htmx.process(confirmBtn);
      }
    }

    // Role edit modal
    if (e.target.id === 'editRoleModal') {
      var roleId      = trigger.dataset.roleId      || '';
      var rolePk      = trigger.dataset.rolePk      || '';
      var parentId    = trigger.dataset.parentId    || '';
      var userCount   = parseInt(trigger.dataset.userCount || '0', 10);
      var hasChildren = trigger.dataset.hasChildren === 'true';
      var nameEl  = document.getElementById('edit_role_name');
      var parEl   = document.getElementById('edit_parent_id');
      var delBtn  = document.getElementById('editRoleDeleteBtn');
      if (nameEl) nameEl.value = roleId;
      if (parEl)  Array.from(parEl.options).forEach(function (o) { o.selected = o.value === parentId; });
      if (delBtn) {
        var title = '';
        if (userCount > 0)   title = userCount + ' users assigned — cannot delete';
        else if (hasChildren) title = 'Has child roles — remove or reassign them first';
        delBtn.disabled = userCount > 0 || hasChildren;
        delBtn.title    = title;
        delBtn.setAttribute('hx-delete', '/admin/access/roles/' + rolePk);
        htmx.process(delBtn);
      }
    }
  });

  // ── Bootstrap modal cleanup after HTMX swaps ────────────────────────────
  // When HTMX replaces <main> while a modal is open (e.g. hx-target="main"),
  // the modal element is torn out of the DOM before Bootstrap's cleanup code
  // can run.  This leaves a .modal-backdrop div and modal-open on <body>,
  // making the page appear frozen.
  //
  // Two-pronged fix:
  //   1. closeModal — fired by HX-Trigger header from the server on success.
  //      Programmatically hides the modal so Bootstrap can clean up itself.
  //   2. htmx:afterSwap — force-removes any orphaned backdrop that slipped
  //      through (e.g. navigating away via sidebar while a modal is open).

  function cleanModalBackdrop() {
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }

  // htmx fires custom events from HX-Trigger on the <body>; listen on document.
  document.addEventListener('closeModal', function () {
    // Hide the currently open modal via Bootstrap so it fires hidden.bs.modal.
    // If the DOM was already swapped before this fires, fall back to brute-force.
    var open = document.querySelector('.modal.show');
    if (open) {
      var instance = bootstrap.Modal.getInstance(open);
      if (instance) {
        open.addEventListener('hidden.bs.modal', cleanModalBackdrop, { once: true });
        instance.hide();
        return;
      }
    }
    cleanModalBackdrop();
  });

  document.addEventListener('htmx:afterSwap', cleanModalBackdrop);

  // ── Shared modal shell cleanup ───────────────────────────────────────────
  // Empty #sharedModalDialog after the modal fully closes so stale content
  // never lingers in the DOM between invocations.
  var sharedModalEl = document.getElementById('sharedModal');
  if (sharedModalEl) {
    sharedModalEl.addEventListener('hidden.bs.modal', function () {
      document.getElementById('sharedModalDialog').innerHTML = '';
    });
  }

  // ── Status toggle buttons (damage-detail page) ───────────────────────────
  // Selecting a status makes that button active and deselects the others.
  const statusBtns = document.querySelectorAll('.ims-status-btn');
  statusBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      statusBtns.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

})();

// code previously used for fixing Tracy debugger during htmx boosted request
(function () {
    let tracyEl = null;

    document.addEventListener('htmx:beforeSwap', function () {
        tracyEl = document.getElementById('tracy-debug');
        if (tracyEl) {
            tracyEl.remove(); // detach before HTMX replaces body innerHTML
        }
    });

    // Use htmx:afterSwap (not afterSettle) to re-attach as early as possible,
    // before Tracy's async _tracy_bar script can fire and call loadAjax().
    document.addEventListener('htmx:afterSwap', function () {
        if (tracyEl) {
            document.body.appendChild(tracyEl);
            tracyEl = null;
        }
    });
})();

// Allow HTMX to swap 4xx/5xx responses (e.g. validation errors returning 422)
document.addEventListener('htmx:beforeSwap', function (evt) {
    if (evt.detail.xhr.status >= 400) {
        evt.detail.shouldSwap = true;
        evt.detail.isError    = false;
    }
});

// ── ACL Wizard controller ────────────────────────────────────────────────────
(function () {
    'use strict';

    var TOTAL_STEPS = 5;

    // Wizard state
    var _state = {
        step:           1,
        routeName:      '',
        methods:        [],
        privs:          [],
        ruleType:       'Allow',
        roleId:         '',
        selectedPrivs:  [],
        assertionAliases: [],
    };

    // ── Helpers ──────────────────────────────────────────────────────────────

    function _getModal() {
        return document.getElementById('protectWizardModal');
    }

    function _bsModal() {
        var el = _getModal();
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    function _showStep(n) {
        _state.step = n;
        for (var i = 1; i <= TOTAL_STEPS; i++) {
            var panel = document.getElementById('wiz-panel-' + i);
            if (panel) panel.classList.toggle('d-none', i !== n);
        }

        // Stepper indicators
        var steps = document.querySelectorAll('#wiz-stepper .ims-acl-wiz-step');
        steps.forEach(function (el, idx) {
            el.classList.remove('active', 'done');
            if (idx + 1 < n)  el.classList.add('done');
            if (idx + 1 === n) el.classList.add('active');
        });

        // Footer buttons
        var back = document.getElementById('ims-acl-wiz-back');
        var next = document.getElementById('ims-acl-wiz-next');
        var save = document.getElementById('ims-acl-wiz-save');
        if (back) back.disabled = (n === 1);
        if (next) next.classList.toggle('d-none', n === TOTAL_STEPS);
        if (save) save.classList.toggle('d-none', n !== TOTAL_STEPS);

        if (n === TOTAL_STEPS) _populateReview();
    }

    function _initWizard(btn) {
        _state.routeName     = btn.dataset.routeName     || '';
        _state.methods       = JSON.parse(btn.dataset.methods || '[]');
        _state.privs         = JSON.parse(btn.dataset.privs   || '[]');
        _state.ruleType      = 'Allow';
        _state.roleId        = '';
        _state.selectedPrivs = [];
        _state.assertionAliases = [];

        // Route context in header
        var routeDisplay = document.getElementById('wiz-route-display');
        if (routeDisplay) routeDisplay.textContent = _state.routeName;

        var methodsSpan = document.getElementById('wiz-header-methods');
        if (methodsSpan) {
            methodsSpan.innerHTML = _state.methods.map(function (m) {
                return '<span class="ims-acl-method-pill ims-acl-method-' + m + '">' + m + '</span>';
            }).join('');
        }

        var privsSpan = document.getElementById('wiz-header-privs');
        if (privsSpan) {
            privsSpan.innerHTML = _state.privs.map(function (p) {
                return '<span class="badge bg-transparent border ims-acl-priv-' + p + ' ims-badge-xs">' + p + '</span>';
            }).join('');
        }

        // Populate privilege cards for step 3 (auto-selected, display-only)
        var privCards = document.getElementById('wiz-priv-cards');
        if (privCards) {
            privCards.innerHTML = _state.privs.map(function (p) {
                return '<div class="ims-acl-priv-card selected-' + p + '" data-priv="' + p + '">'
                     + '<i class="bi bi-key-fill me-1"></i>' + p
                     + '</div>';
            }).join('');
        }
        _state.selectedPrivs = _state.privs.slice();

        // Reset assertion cards
        var noneAssert = document.querySelector('[data-assertion="none"]');
        if (noneAssert) _toggleAssertion(noneAssert);

        // Reset grant cards
        document.querySelectorAll('.ims-acl-grant-card').forEach(function (c) { c.classList.remove('selected'); });

        // Reset role tree
        document.querySelectorAll('.ims-acl-role-item').forEach(function (el) {
            el.classList.remove('selected');
            el.setAttribute('aria-selected', 'false');
        });

        // Reset search inputs
        var roleSearch = document.getElementById('ims-acl-role-search');
        if (roleSearch) { roleSearch.value = ''; _filterRoleTree(''); }

        // Propagation alert
        var propAlert = document.getElementById('wiz-propagation-alert');
        if (propAlert) propAlert.classList.add('d-none');

        // Hidden inputs
        document.getElementById('wiz-input-route-name').value = _state.routeName;
        document.getElementById('wiz-input-rule-type').value  = 'Allow';

        _showStep(1);
    }

    // ── Grant type (step 1) ──────────────────────────────────────────────────

    function _selectGrant(el) {
        document.querySelectorAll('.ims-acl-grant-card').forEach(function (c) { c.classList.remove('selected'); });
        el.classList.add('selected');
        _state.ruleType = el.dataset.aclStepGrant || 'Allow';
        document.getElementById('wiz-input-rule-type').value = _state.ruleType;
    }

    // ── Role tree (step 2) ───────────────────────────────────────────────────

    function _getRoleChildren() {
        try {
            var modal = _getModal();
            return modal ? JSON.parse(modal.dataset.roleChildren || '{}') : {};
        } catch (e) {
            return {};
        }
    }

    function _selectRole(el) {
        document.querySelectorAll('.ims-acl-role-item').forEach(function (r) {
            r.classList.remove('selected');
            r.setAttribute('aria-selected', 'false');
        });
        el.classList.add('selected');
        el.setAttribute('aria-selected', 'true');
        _state.roleId = el.dataset.roleId || '';
        document.getElementById('wiz-input-role-id').value = _state.roleId;


    }

    function _filterRoleTree(query) {
        var q = query.toLowerCase();
        document.querySelectorAll('.ims-acl-role-item').forEach(function (el) {
            var label    = (el.dataset.roleId  || '').toLowerCase();
            var ancestry = (el.dataset.ancestry || '').toLowerCase();
            el.style.display = (!q || label.includes(q) || ancestry.includes(q)) ? '' : 'none';
        });
    }


    // ── Assertion (step 4) ───────────────────────────────────────────────────

    function _syncAssertionInputs() {
        var container = document.getElementById('wiz-assertion-inputs');
        if (!container) return;
        container.innerHTML = '';
        if (_state.assertionAliases.length === 1) {
            var input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'assertions';
            input.value = _state.assertionAliases[0];
            container.appendChild(input);
        } else if (_state.assertionAliases.length > 1) {
            _state.assertionAliases.forEach(function (alias) {
                var input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'assertions[]';
                input.value = alias;
                container.appendChild(input);
            });
        }
        // Empty array: no inputs sent
    }

    function _toggleAssertion(el) {
        var isNone = el.dataset.assertion === 'none';
        if (isNone) {
            document.querySelectorAll('.ims-acl-assertion-card').forEach(function (c) { c.classList.remove('selected'); });
            el.classList.add('selected');
            _state.assertionAliases = [];
        } else {
            var alias = el.dataset.assertionAlias || '';
            var idx   = _state.assertionAliases.indexOf(alias);
            if (idx !== -1) {
                _state.assertionAliases.splice(idx, 1);
                el.classList.remove('selected');
                if (_state.assertionAliases.length === 0) {
                    var noneCard = document.querySelector('[data-assertion="none"]');
                    if (noneCard) noneCard.classList.add('selected');
                }
            } else {
                var noneCard = document.querySelector('[data-assertion="none"]');
                if (noneCard) noneCard.classList.remove('selected');
                _state.assertionAliases.push(alias);
                el.classList.add('selected');
            }
        }
        _syncAssertionInputs();
    }

    // ── Review (step 5) ──────────────────────────────────────────────────────

    function _populateReview() {
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        set('wiz-review-route',  _state.routeName);
        set('wiz-review-role',   _state.roleId || '(none selected)');

        var typeEl = document.getElementById('wiz-review-type');
        if (typeEl) {
            typeEl.innerHTML = _state.ruleType === 'Allow'
                ? '<span class="badge bg-success-subtle border border-success-subtle text-success-emphasis">Allow</span>'
                : '<span class="badge bg-danger-subtle border border-danger-subtle text-danger-emphasis">Deny</span>';
        }

        var privsEl = document.getElementById('wiz-review-privs');
        if (privsEl) {
            privsEl.innerHTML = _state.selectedPrivs.length
                ? _state.selectedPrivs.map(function (p) {
                    return '<span class="badge bg-transparent border ims-acl-priv-' + p + ' ims-badge-xs">' + p + '</span>';
                  }).join('')
                : '<span class="text-secondary">(none)</span>';
        }

        var assertRow = document.getElementById('wiz-review-assert-row');
        var assertEl  = document.getElementById('wiz-review-assertion');
        if (_state.assertionAliases.length > 0) {
            if (assertRow) assertRow.classList.remove('d-none');
            if (assertEl)  assertEl.textContent = _state.assertionAliases.join(', ');
        } else {
            if (assertRow) assertRow.classList.add('d-none');
        }
    }

    // ── Step navigation ──────────────────────────────────────────────────────

    function _canAdvance() {
        if (_state.step === 1) return !!document.querySelector('.ims-acl-grant-card.selected');
        if (_state.step === 2) return !!_state.roleId;
        return true;
    }

    // ── Route list filter ────────────────────────────────────────────────────

    var _activeFilter = 'all';

    function _filterRoutes() {
        var query  = (document.getElementById('ims-acl-route-search') || {}).value || '';
        var q = query.toLowerCase();
        document.querySelectorAll('.ims-acl-route-entry').forEach(function (row) {
            var name   = (row.dataset.routeName || '').toLowerCase();
            var status = row.dataset.status || 'unprotected';
            var matchFilter = (_activeFilter === 'all') || (_activeFilter === status);
            var matchSearch = !q || name.includes(q);
            row.style.display = (matchFilter && matchSearch) ? '' : 'none';

            // Hide/show the sibling rules panel too
            var next = row.nextElementSibling;
            if (next && next.classList.contains('ims-acl-rules-panel')) {
                if (!matchFilter || !matchSearch) next.classList.add('d-none');
            }
        });
    }

    // ── Init & event delegation ──────────────────────────────────────────────

    function initAclPage() {
        var modal = _getModal();
        if (!modal) return; // not on the ACL page

        // Wizard trigger buttons (both "Protect" and "Add rule")
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-wizard-action]');
            if (btn) {
                _initWizard(btn);
                _bsModal().show();
                return;
            }

            // Rules offcanvas trigger
            var offcanvasTrigger = e.target.closest('.ims-acl-rules-offcanvas-trigger');
            if (offcanvasTrigger) {
                var panelId   = offcanvasTrigger.dataset.rulesPanel;
                var routeName = offcanvasTrigger.dataset.routeName;
                var source    = panelId ? document.getElementById(panelId) : null;
                var body      = document.getElementById('ims-acl-offcanvas-body');
                var titleEl   = document.getElementById('ims-acl-offcanvas-route-name');
                if (titleEl) titleEl.textContent = routeName || '';
                if (body) {
                    body.innerHTML = source ? source.innerHTML : '<p class="text-secondary small">No rules found.</p>';
                    htmx.process(body);
                }
                bootstrap.Offcanvas.getOrCreateInstance(
                    document.getElementById('ims-acl-rule-offcanvas')
                ).show();
                return;
            }

            // Filter buttons
            var filterBtn = e.target.closest('[data-acl-filter]');
            if (filterBtn) {
                _activeFilter = filterBtn.dataset.aclFilter || 'all';
                document.querySelectorAll('[data-acl-filter]').forEach(function (b) { b.classList.remove('active'); });
                filterBtn.classList.add('active');
                _filterRoutes();
                return;
            }

            // Step 1 — grant card
            var grantCard = e.target.closest('[data-acl-step-grant]');
            if (grantCard && modal.contains(grantCard)) {
                _selectGrant(grantCard);
                return;
            }

            // Step 2 — role item (stop at <ul> boundary)
            var roleItem = e.target.closest('.ims-acl-role-item');
            if (roleItem && modal.contains(roleItem)) {
                _selectRole(roleItem);
                return;
            }

            // Step 4 — assertion card
            var assertCard = e.target.closest('.ims-acl-assertion-card');
            if (assertCard && modal.contains(assertCard)) {
                _toggleAssertion(assertCard);
                return;
            }

            // Wizard Next
            if (e.target.closest('#ims-acl-wiz-next')) {
                if (_canAdvance() && _state.step < TOTAL_STEPS) {
                    _showStep(_state.step + 1);
                }
                return;
            }

            // Wizard Back
            if (e.target.closest('#ims-acl-wiz-back')) {
                if (_state.step > 1) _showStep(_state.step - 1);
                return;
            }
        });

        // Route search input
        document.addEventListener('input', function (e) {
            if (e.target.id === 'ims-acl-route-search') {
                _filterRoutes();
                return;
            }
            // Role tree search
            if (e.target.id === 'ims-acl-role-search') {
                _filterRoleTree(e.target.value);
                return;
            }
        });

        // Rule type / assertion radio changes


        // Keyboard support for grant/role/priv/assertion cards
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var t = e.target;
            if (t.matches('[data-acl-step-grant]') && modal.contains(t)) { e.preventDefault(); _selectGrant(t); }
            if (t.matches('.ims-acl-role-item')    && modal.contains(t)) { e.preventDefault(); _selectRole(t); }
            if (t.matches('.ims-acl-assertion-card') && modal.contains(t)) { e.preventDefault(); _toggleAssertion(t); }
        });
    }

    // Hide the rules offcanvas before any HTMX swap so Bootstrap can remove
    // its backdrop cleanly (the offcanvas node lives inside <main> and would
    // otherwise be ripped out of the DOM while still "shown").
    document.addEventListener('htmx:beforeRequest', function () {
        var oc = document.getElementById('ims-acl-rule-offcanvas');
        if (!oc) return;
        var instance = bootstrap.Offcanvas.getInstance(oc);
        if (instance) instance.hide();
    });

    initAclPage();
    document.addEventListener('htmx:afterSettle', function () {
        initAclPage();
        // Re-wire the rules offcanvas instance after every HTMX body swap
        var oc = document.getElementById('ims-acl-rule-offcanvas');
        if (oc) bootstrap.Offcanvas.getOrCreateInstance(oc);
    });
})();
