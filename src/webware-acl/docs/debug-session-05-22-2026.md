# Errors

## Step debug insights

When making request to http://localhost:8080

1. AuthorizationMiddleware.php

```php
if (! $this->acl->isAllowedRoute($user, $routeResource)) { // $user = GuestUser
    return $this->forbiddenHandler->handle($request); // ForbiddenHandler is reached
}
```

2. Acl.php

In Acl the hasResource check passes and $this->isAllowed is reached

3. There is no role mismatch 

4. ForbiddenHandler

```php
if ($user->isGuest()) { // true
    return new RedirectResponse($this->loginPath); // /user.manager/login
}
```

 **Bonus**
 All roles, resources, are being added correctly or so it appears but I did get a failure for resource user.admin.usermanager.

At some point in the redirect cycle I get this exception

Exception has occurred: Laminas\Permissions\Acl\Exception\InvalidArgumentException
Resource 'dashboard' not found

I then get a few undefined variable $resource from the Laminas Acl class in a row

Then this exception is thrown

Exception has occurred: Laminas\Permissions\Acl\Exception\InvalidArgumentException
Resource 'webware.admin.user.manager' not found

Several more undefined $resource's are thrown

Then
Exception has occurred: Laminas\Permissions\Acl\Exception\InvalidArgumentException
Resource 'webware.admin.user.manager.create' not found

Then it exits into the login page.

## Posting to Login Page

Upon entering email and password and clicking Sign In

## Key Finding

After logging in the RouteResource::$request instance holds a
GuestUser instance populated with MY data ie identity = jsmith@webinertia.net, $roles = ['Developer'], etc

1st bug that must be tracked down is how is my data ending up populating a GuestUser instance instead of a User instance.

You need to find me the answer to that IMMEDIATELY!

## Improvements

* AclFactory
Webware-acl needs a AclRule string enum with 2 cases allow/deny which can be used to filter what type of rule is being passed this will allow the factory to filter that key and our config files can use the ->value syntax as an example of usage to prevent typos and to provide/enforce a type for the rule type. I will handle the refactor for this.