<?php

declare(strict_types=1);


namespace Webware\Acl\Admin\RequestHandler;

use Htmx\Response\Header;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webware\Acl\AclInterface;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;

use function json_encode;

/**
 * Handles GET /admin/access/rules — flat rules table, or hierarchy view when
 * both ?resource and ?privilege are set.
 * Handles POST /admin/access/rules — create a new rule.
 * Handles PATCH /admin/access/rules/{id} — switch allow↔deny on existing rule.
 * Handles DELETE /admin/access/rules/{id} — remove a rule.
 */
final class RuleManagerHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly array $config,
        private readonly TemplateRendererInterface $template,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $roles       = [];
        $roleParents = [];
        $resources   = [];
        $privileges  = [];
        $rules       = [];
        $assertions  = [];

        $query          = $request->getQueryParams();
        $filterRole     = isset($query['role'])      && $query['role']      !== '' ? (string) $query['role']      : null;
        $filterResource = isset($query['resource'])  && $query['resource']  !== '' ? (string) $query['resource']  : null;
        $filterPrivilege = isset($query['privilege']) && $query['privilege'] !== '' ? (string) $query['privilege'] : null;
        $filterType     = isset($query['type'])      && $query['type']      !== '' ? (string) $query['type']      : null;

        // Build maps used by both the flat table and hierarchy view.
        // roleIdToPk: roleId string → integer PK
        $roleIdToPk = [];
        foreach ($roles as $pk => $role) {
            $roleIdToPk[$role->roleId] = $pk;
        }

        // Build parent map: child role PK → list of parent roleId strings (for display)
        $roleParentIds = [];
        foreach ($roleParents as $childPk => $parentPks) {
            foreach ($parentPks as $parentPk) {
                if (isset($roles[$parentPk])) {
                    $roleParentIds[$childPk][] = $roles[$parentPk]->roleId;
                }
            }
        }

        // ── Hierarchy view ────────────────────────────────────────────────────
        // Activated when both resource AND privilege filters are set.
        // Computes the effective rule state for every role in topological order
        // (ancestors first) so the template can render the full chain.
        //
        // Each entry in $hierarchyRows:
        //   role_id       string   — role identifier
        //   role_pk       int      — role PK
        //   depth         int      — nesting depth for visual indent
        //   parent_ids    string[] — parent roleId strings (for badge display)
        //   state         string   — 'explicit_allow'|'explicit_deny'|'inherited_allow'|'inherited_deny'|'none'
        //   rule_id       int|null — PK of the explicit rule row (if state is explicit_*)
        //   via           string   — roleId where the inherited rule comes from (if state is inherited_*)
        $hierarchyRows = null;
        if ($filterResource !== null && $filterPrivilege !== null) {
            // Index explicit rules for this resource+privilege: roleId → rule row
            $explicitByRole = [];
            foreach ($rules as $rule) {
                if ($rule['resource_id'] === $filterResource && $rule['privilege_id'] === $filterPrivilege) {
                    $explicitByRole[$rule['role_id']] = $rule;
                }
            }

            // Build descendant map: parent PK → list of child PKs (for redundancy detection)
            $children = [];
            foreach ($roleParents as $childPk => $parentPks) {
                foreach ($parentPks as $parentPk) {
                    $children[$parentPk][] = $childPk;
                }
            }

            // Topological sort (Kahn's algorithm) — ancestors before descendants
            $inDegree = [];
            foreach ($roles as $pk => $role) {
                $inDegree[$pk] = count($roleParents[$pk] ?? []);
            }
            $queue = [];
            foreach ($inDegree as $pk => $deg) {
                if ($deg === 0) {
                    $queue[] = $pk;
                }
            }
            $sorted = [];
            while ($queue !== []) {
                $current = array_shift($queue);
                $sorted[] = $current;
                foreach ($children[$current] ?? [] as $childPk) {
                    $inDegree[$childPk]--;
                    if ($inDegree[$childPk] === 0) {
                        $queue[] = $childPk;
                    }
                }
            }

            // Walk sorted order; propagate effective state down the chain.
            // effectiveState: rolePk → ['state' => string, 'via' => string]
            $effectiveState = [];
            foreach ($sorted as $pk) {
                if (!isset($roles[$pk])) {
                    continue;
                }
                $roleId = $roles[$pk]->roleId;

                if (isset($explicitByRole[$roleId])) {
                    $effectiveState[$pk] = [
                        'state' => 'explicit_' . $explicitByRole[$roleId]['type'],
                        'via'   => $roleId,
                    ];
                    continue;
                }

                // Inherit from parents — use first parent that has a resolved state
                $inherited = null;
                foreach ($roleParents[$pk] ?? [] as $parentPk) {
                    if (isset($effectiveState[$parentPk])) {
                        $inherited = $effectiveState[$parentPk];
                        break;
                    }
                }

                if ($inherited !== null) {
                    $effectiveState[$pk] = [
                        'state' => str_replace('explicit_', 'inherited_', str_replace('inherited_', 'inherited_', $inherited['state'])),
                        'via'   => $inherited['via'],
                    ];
                } else {
                    $effectiveState[$pk] = ['state' => 'none', 'via' => ''];
                }
            }

            // Compute depth for visual indentation (BFS from roots)
            $depth = [];
            $bfsQueue = [];
            foreach ($sorted as $pk) {
                if (($roleParents[$pk] ?? []) === []) {
                    $depth[$pk]   = 0;
                    $bfsQueue[]   = $pk;
                }
            }
            while ($bfsQueue !== []) {
                $current = array_shift($bfsQueue);
                foreach ($children[$current] ?? [] as $childPk) {
                    $depth[$childPk] = ($depth[$current] ?? 0) + 1;
                    $bfsQueue[] = $childPk;
                }
            }

            // Build descendant lookup: rolePk → all descendant PKs (for redundancy check)
            $allDescendants = [];
            foreach ($sorted as $pk) {
                $allDescendants[$pk] = [];
                foreach ($sorted as $candidate) {
                    // candidate is a descendant of pk if pk appears in its ancestor chain
                    $visited   = [];
                    $walkQueue = $roleParents[$candidate] ?? [];
                    while ($walkQueue !== []) {
                        $anc = array_shift($walkQueue);
                        if ($anc === $pk) {
                            $allDescendants[$pk][] = $candidate;
                            break;
                        }
                        if (!isset($visited[$anc])) {
                            $visited[$anc] = true;
                            foreach ($roleParents[$anc] ?? [] as $grandAnc) {
                                $walkQueue[] = $grandAnc;
                            }
                        }
                    }
                }
            }

            // Assemble hierarchy rows
            $hierarchyRows = [];
            foreach ($sorted as $pk) {
                if (!isset($roles[$pk])) {
                    continue;
                }
                $roleId    = $roles[$pk]->roleId;
                $eff       = $effectiveState[$pk];
                $ruleId    = null;
                if (str_starts_with($eff['state'], 'explicit_') && isset($explicitByRole[$roleId])) {
                    $ruleId = $explicitByRole[$roleId]['id'];
                }

                // Redundant descendants: descendants that already have an explicit rule
                $redundantDescendants = [];
                // Elevated descendants: descendants with no rule of their own (will gain access)
                $elevatedDescendants  = [];
                foreach ($allDescendants[$pk] as $descPk) {
                    if (!isset($roles[$descPk])) {
                        continue;
                    }
                    $descRoleId = $roles[$descPk]->roleId;
                    if (isset($explicitByRole[$descRoleId])) {
                        $redundantDescendants[] = $descRoleId . ' (explicit ' . $explicitByRole[$descRoleId]['type'] . ')';
                    } elseif (($effectiveState[$descPk]['state'] ?? 'none') === 'none') {
                        $elevatedDescendants[] = $descRoleId;
                    }
                }

                $hierarchyRows[] = [
                    'role_id'               => $roleId,
                    'role_pk'               => $pk,
                    'depth'                 => $depth[$pk] ?? 0,
                    'parent_ids'            => $roleParentIds[$pk] ?? [],
                    'state'                 => $eff['state'],
                    'rule_id'               => $ruleId,
                    'via'                   => $eff['via'],
                    'redundant_descendants' => $redundantDescendants,
                    'elevated_descendants'  => $elevatedDescendants,
                    'assertions'            => $ruleId !== null ? ($assertions[$ruleId] ?? []) : [],
                ];
            }
        }

        // ── Flat table (no hierarchy) ─────────────────────────────────────────
        // BFS over roleParents to collect ancestor roleId strings for the filtered role
        $filterRoleAncestorIds = [];
        if ($filterRole !== null) {
            $filteredPk = $roleIdToPk[$filterRole] ?? null;
            if ($filteredPk !== null) {
                $queue   = $roleParents[$filteredPk] ?? [];
                $visited = [];
                while ($queue !== []) {
                    $ancestorPk = array_shift($queue);
                    if (isset($visited[$ancestorPk])) {
                        continue;
                    }
                    $visited[$ancestorPk] = true;
                    if (isset($roles[$ancestorPk])) {
                        $filterRoleAncestorIds[] = $roles[$ancestorPk]->roleId;
                    }
                    foreach ($roleParents[$ancestorPk] ?? [] as $grandparentPk) {
                        $queue[] = $grandparentPk;
                    }
                }
            }
        }

        if ($hierarchyRows === null && ($filterRole !== null || $filterResource !== null || $filterType !== null)) {
            $rules = array_filter(
                $rules,
                static function (array $rule) use ($filterRole, $filterRoleAncestorIds, $filterResource, $filterType): bool {
                    if ($filterRole !== null) {
                        $isOwnRule       = $rule['role_id'] === $filterRole;
                        $isInheritedRule = in_array($rule['role_id'], $filterRoleAncestorIds, true);
                        if (! $isOwnRule && ! $isInheritedRule) {
                            return false;
                        }
                    }
                    if ($filterResource !== null && $rule['resource_id'] !== $filterResource) {
                        return false;
                    }
                    if ($filterType !== null && $rule['type'] !== $filterType) {
                        return false;
                    }
                    return true;
                },
            );
        }

        $response = new HtmlResponse($this->template->render('acl::admin-rules', [
            'roles'                 => $roles,
            'resources'             => $resources,
            'privileges'            => $privileges,
            'rules'                 => $rules,
            'filterRole'            => $filterRole,
            'filterResource'        => $filterResource,
            'filterPrivilege'       => $filterPrivilege,
            'filterType'            => $filterType,
            'filterRoleAncestorIds' => $filterRoleAncestorIds,
            'hierarchyRows'         => $hierarchyRows,
        ]));

        $commandResult = $request->getAttribute(CommandResult::class);
        if ($commandResult instanceof CommandResult && $commandResult->getStatus() === CommandStatus::Success) {
            $response = $response->withHeader(Header::Trigger->value, json_encode(['closeModal' => null]));
        }

        return $response;
    }
}
