# Abtract

How best to seed data to the acl?

The Acl Build pipeline is completely event driven which means its extremely flexible, by design. Going forward I think the best solution is to use this flexibility to our advantage. Since the refactor (my refactor) the route names (the actual resources) have become partially dynamic in the sense that the developer can, through the laminas config service, choose the base path for which the webware-admin module will publish its "dashboard".

This provides tremendous flexiblity in respect to preventing route/resourceId collisions with existing systems but it also significantly increases the complexity of the inter-communication of the webware ecosystem modules. However, after careful consideration the only sensible solution is to continue with the "Protect" route/resource path we established in your last refactor. The key difference is how we seed the DB. We are only concerned with seeding data for the 3 inter-dependent components. Those are

* Webware Admin webware-admin
* Webware ACL webware-acl
* Webware UserManager webware-usermanager

Of the 3 UserManager is the most complicated due to it requiring non http verb mapped privileges. However, since what I am proposing is an event driven self-seeding acl build pipeline it will be rather trivial to implement. The solution, once again is the laminas config service. The UserManager's ConfigProvider will provide the seed data and its acl build pipeline listeners will handle the data. Since the ConfigProvider run on every request will need a db flag so that we can flag the db that the initial seeding has been done. After that initial load post install it will then be solely managed by the caching mechanism that is already in place. This should solve the majority of our current blockers.

## Reference from Laminas ACL docs

> For the purposes of this documentation:

* a resource is an object to which access is controlled.
* a role is an object that may request access to a resource.

> Put simply, roles request access to resources. For example, if a parking attendant requests access to a car, then the parking attendant is the requesting role, and the car is the resource, since access to the car may not be granted to everyone.

> Laminas\Permissions\Acl\Acl provides a tree structure to which multiple resources can be added. Since resources are stored in such a tree structure, they can be organized from the general (toward the tree root) to the specific (toward the tree leaves). Queries on a specific resource will automatically search the resource's hierarchy for rules assigned to ancestor resources, allowing for simple inheritance of rules. For example, if a default rule is to be applied to each building in a city, one would simply assign the rule to the city, instead of assigning the same rule to each building. Some buildings may require exceptions to such a rule, however, and this can be achieved in Laminas\Permissions\Acl\Acl by assigning such exception rules to each building that requires such an exception. A resource may inherit from only one parent resource, though this parent resource can have its own parent resource, etc.

> Laminas\Permissions\Acl\Acl also supports privileges on resources (e.g., "create", "read", "update", "delete"), so the developer can assign rules that affect all privileges or specific privileges on one or more resources.

* In version 1 of ACL Manager we will only support role inheritance within those associations that are loaded by listener.

## Brief Example

* {prefixes.}acl.manager | root route/resource | requires read | mapped by GET
  * {prefixes.}acl.manager.role | child of *.manager | parent of crud | inherits read | mapped by GET
    * {prefixes.}acl.manager.role.create | child of *.role | inherits read | requires create | mapped by POST
    * {prefixes.}acl.manager.role.update | child of *role | inherits read | requires update | mapped by PATCH/PUT
    * {prefixes.}acl.manager.role.delete | child of *role | inherits read | requires delete | mapped by DELETE
* Sibling Manager Primary resource / route (repeat of above)
  * Sibling child resource | Sibling CRUD entry resource
    * And so on and so on.

## Summary

The only minor gaps I can really see in my implementation/minor refactor plan is that the tracking of and assigning of "parent" resources/route names will be a fairly complex problem space due to how we are collecting them at runtime. In v2 I can see a way to detect that the application also has webware-navigation installed. If the nav component is installed or we detect the route options for parent child resources we could then we could use a FilteringIterator to assemble the tree but I am reluctant to include that work in v1 due to its complexity.

/agent Please identify any gaps in the preceeding details. This doc is to serve as the definitive authority on design decisions. It supersedes all other documents.

Response:
1. The DB flag — not specified
"A DB flag" is mentioned but not defined. The schema already has acl_version (021_acl_version.sql) — is that the flag, or is a new per-module seed-status mechanism needed? If it's acl_version, how does it distinguish "seeded" from "version bumped by a rule change"?

2. Trigger event — not specified
Which event fires the seed-check? Something needs to interrogate the flag and conditionally insert data before the DB resources are loaded. If it fires inside buildFromArrays() before RolesLoadedEvent, that needs to be a new named event. The ordering is critical.

3. ConfigProvider data structure — not defined
The document says ConfigProvider will "provide the seed data" but doesn't define the array shape, the config key it lives under, or what service/listener reads it.

4. DB schema for parent-child hierarchy
The Brief Example shows a tree (acl.manager → acl.manager.role → acl.manager.role.create), but the current acl_resource table is flat — no parent_id column. Either the schema needs a parent column, or the hierarchy is in-memory only. Not resolved.

5. Idempotency / upgrade path
The flag means "seed once, never again." But resource IDs just changed (admin.acl → webware.admin.acl.manager). There's no migration strategy described for when seeded values need to change after initial install.

6. Relationship to existing listeners
RegisterAclResourcesListener and RegisterAclRulesListener run on every ACL build (in-memory). The self-seed mechanism runs once (DB). The document doesn't clarify whether these listeners become redundant after seeding, or continue to serve a different purpose (in-memory only, no DB).

7. UserManager non-HTTP privileges
Called out as the most complex case but not elaborated. No structure proposed yet.

## 05/18/2026 Privilege association gaps

Currently our route names include the "crud action" ie create, read, update, delete.

The problem?

They should not, or more correctly they already do by the http method routing.

Put another way, there is no construct to provide the privilegeId. Laminas ACL does not provide a privilegeId or an interface for the contract. We provide one and it will be used. RouteResource should be the only implementor.

Our UI wizard will update the configuration arrays (our acl rules) and add them to the roles we are granting them too. Or, our listeners can grant them during the initial build. Or both, because we will have to support both.

Here's how its supposed to work.

Basic role, resource, privilege workflow from a listener.

Our current RegisterUserManagerRulesListener

```php

$event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'session.create');

```

What it should be.

```php

$event->acl->allow(self::GUEST_ROLE, $this->routeNamePrefix . 'session', ['read', 'create']);

```

Request comes into the application

Hits the identity middleware, user is not authentication so a default guest role is assigned.

Pipeline reaches the authorizing dispatch middleware.

RouteResult gets populated. Passed to our ACL wrapper which knows the PrivilegeProviderInterface

getPrivilegeId() is called
{
  http -> privilege map is consulted
  GET is mapped to read
  read is returned
}

Our wrapper passes the returned read as the privilege to the laminas acl isAllowed

Check passes or fails.
