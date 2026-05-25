# Overview

Here is what we know is true.

* Resources are route names. Only developers can create them because currently there is no webware-* mechanism to generate code, ie at minimum a handler and its route definition via the UI. So resources / routes are totally owned by the developer and exposed only via code.

* Roles are just entries in the config array. These can be fully managed by the UI but base roles can never be deleted since they will always be provided by the ConfigProviders and config aggregation means the base rules will always be available. If a role is to be removed it should be only by the developer, since they are the effective source of truth. Roles that are created by the UI can also be removed by the UI since they will not be reseeded by a config provider. 

* Allow/Deny, since our resources are pulling double duty, ie they are essentially where the grant happens. What our system provides is a base "manager" route. IE the base resource webware.admin.acl.manager, the sub "manager" utility routes, ie the create, read, update, delete essentially serve as privileged resources that our allow/deny workflow will govern on a per role basis.

## Abstract

The RouteCollector will serve as our single source of truth for available resources for which we need to provide a UI workflow to govern the allow/deny rules for. We will provide the needed middleware/handler endpoints to update the allow/deny for a given resource.

As an example - One of our next task will be the manifest workflow. We will provide something like ims.manifest.manager - just as an example we may grant allow to Warehouse role as well as create, update but maybe only Warehouse Supervisor will be able to delete a manifest. All of the resources will be provided by the RouteCollector and they will be added simply by creating the route, at which time they will automatically become available to the UI for managing.

It's possible that we can provide a custom top level key in the container to register Assertions under, which would provide a easy way to provide a list of registered assertions for assignment via the UI. Not 100% sure this will even be needed. Could well end up that assertions should be a developer only thing like "adding resources".
