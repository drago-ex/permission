## Drago Permission
Lightweight ACL and role management.

The package provides a central ACL factory, modular permission registration per module,
and automatic authorization checks in presenters.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://raw.githubusercontent.com/drago-ex/permission/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fpermission.svg)](https://badge.fury.io/ph/drago-ex%2Fpermission)
[![Coding Style](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml)

## Requirements
- PHP >= 8.3
- Nette Framework
- Composer

## Installation
```bash
composer require drago-ex/permission
```

## Features
- Central ACL creation
- Modular permission providers per module
- Default roles: guest, user, admin
- Automatic presenter authorization
- Action and signal based privileges

## Roles
Default roles:

- guest
- user (inherits from guest)
- admin (inherits from user)

Roles are registered automatically.

## Permission Factory
PermissionFactory creates a Nette\Security\Permission instance,
registers default roles, and runs all registered permission providers.

Providers are collected via DI tags.

## Permission Providers
Each module registers its own permissions using a Provider implementation.

Providers:
- register ACL resources
- define allow / deny rules
- live inside the module they belong to

Typical resource naming:
- Backend:Sign
- Frontend:Article

Example provider for a Sign module:

```php
use Drago\Permission\Provider;
use Drago\Permission\Role;
use Nette\Security\Permission;

final class SignPermission implements Provider
{
	private const string Resource = 'Backend:Sign';


	public function register(Permission $acl): void
	{
		$acl->addResource(self::Resource);
		$acl->allow(Role::RoleGuest, self::Resource);
	}
}
```

This registers the `Backend:Sign` resource and grants access to guests (unauthenticated users),
which is the minimum required for the login page to be accessible.

## DI Configuration
Permission factory:
```neon
services:
	permissionFactory:
		class: Drago\Permission\PermissionFactory
		arguments: [tagged(PermisionTag)]

	- @permissionFactory::create
```

Module provider:
```neon
services:
	signPermission:
		class: App\Modules\Sign\SignPermission
		tags: [PermisionTag]
```

For larger applications with many providers, you can use the `search` section to register all matching classes automatically instead of listing each one individually:
```neon
search:
	permissions:
		in: %appDir%/UI
		classes: [*Permission]
		tags: [PermisionTag]
```

## Presenter Authorization
Authorization is handled by the `Authorization` trait.

- runs automatically on presenter startup
- checks ACL using presenter name and resolved privilege

To activate authorization in a presenter, include the trait:

```php
use Drago\Permission\Authorization;

class BasePresenter extends Nette\Application\UI\Presenter
{
	use Authorization;
}
```

All presenters extending `BasePresenter` will then have automatic authorization checks applied.

Unauthorized access:
- not logged in → redirect to Sign:in
- logged in but forbidden → HTTP 403

### Privilege resolution

The trait automatically resolves which ACL privilege to check based on the current request:

| Situation                        | Resolved privilege  |
|----------------------------------|---------------------|
| Page load (no signal)            | `{action}-read`     |
| Signal from a read-only receiver | `{component}-read`  |
| Signal listed as read-only       | `{component}-read`  |
| Any other signal                 | `{component}-write` |
| Direct presenter signal (no component) | `{signal}`    |

### Read-only signals

Override `readOnlySignals()` to declare signal names that should be treated as read operations (e.g. sorting, pagination):

```php
protected function readOnlySignals(): array
{
	return ['sort', 'page', 'itemsPerPage'];
}
```

### Read-only receivers

Override `readOnlyReceivers()` to declare component name substrings whose signals should always resolve to read privilege (e.g. a data grid that only displays data):

```php
protected function readOnlyReceivers(): array
{
	return ['articleGrid', 'userGrid'];
}
```

Any signal whose receiver name contains one of these strings will be resolved as `{component}-read` regardless of the signal name.

### Full example

```php
use Drago\Permission\Authorization;

class ArticlePresenter extends BasePresenter
{
	protected function readOnlySignals(): array
	{
		// these signals only read data → checked as "{component}-read"
		return ['sort', 'page'];
	}

	protected function readOnlyReceivers(): array
	{
		// any signal from a receiver containing "Grid" → checked as "{component}-read"
		return ['Grid'];
	}
}
```

With this configuration the ACL check for `articleGrid:sort` resolves to `articleGrid-read`,
while `articleGrid:delete` resolves to `articleGrid-write`.
