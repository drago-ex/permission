# Drago Permission

Lightweight ACL and role management.
The package provides a central ACL factory, modular permission registration per module,
and automatic authorization checks in presenters.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/drago-ex/permission/blob/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fpermission.svg)](https://badge.fury.io/ph/drago-ex%2Fpermission)
[![Tests](https://github.com/drago-ex/permission/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/tests.yml)
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

## Related Package: Dynamic Role Management
For dynamic role and access management, use:

- `drago-ex/project-permission`: https://github.com/drago-ex/project-permission

This package is built on top of `drago-ex/permission` and provides:

- role creation
- assigning roles to users
- allowing or denying access per role

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

## Permission Generator (CLI)
The package provides a generator for module providers:

```bash
vendor/bin/create-permission
```

General usage:

```bash
vendor/bin/create-permission <ClassName> <Namespace> [Resource] [OutputDir] [options]
```

Example for `SignPermission`:

```bash
vendor/bin/create-permission SignPermission App\UI\Backend\Sign Backend:Sign app/UI/Backend/Sign
```

Example for `AdminPermission`:

```bash
vendor/bin/create-permission AdminPermission App\UI\Backend\Admin Backend:Admin app/UI/Backend/Admin --allow-role=RoleAdmin --allow-with-resource=0
```

### Options

- `--allow-role=<RoleConstant>` default `RoleGuest`
- `--allow-privilege=<string>` optional privilege argument for `allow()`
- `--add-resource=<0|1>` default `1` (generate `$acl->addResource(...)`)
- `--allow-with-resource=<0|1>` default `1` (generate `allow(..., self::Resource)`)
- `--allow=<rule>` repeatable, custom allow rules
- `--force` overwrite existing file

`--allow` formats:

- `--allow=RoleAdmin`
- `--allow=RoleUser,self::Resource`
- `--allow=RoleUser,self::Resource,default`

Multi-rule example:

```bash
vendor/bin/create-permission AdminPermission App\UI\Backend\Admin Backend:Admin app/UI/Backend/Admin --allow=RoleAdmin --allow=RoleUser,self::Resource,default
```

Generated `register()` example:

```php
public function register(Permission $acl): void
{
	$acl->addResource(self::Resource);
	$acl->allow(Role::RoleAdmin);
	$acl->allow(Role::RoleUser, self::Resource, 'default');
}
```

### Module Wrapper Scripts

For one-command generation per module, add a local wrapper script in your app, e.g. `bin/create-admin-permission`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/vendor/bin/create-permission';

$args = [
	'AdminPermission',
	'App\\UI\\Backend\\Admin',
	'Backend:Admin',
	'app/UI/Backend/Admin',
	'--allow=RoleAdmin',
	'--allow=RoleUser,self::Resource,default',
];

$command = 'php ' . escapeshellarg($script);
foreach ($args as $arg) {
	$command .= ' ' . escapeshellarg($arg);
}

passthru($command, $exitCode);
exit($exitCode);
```

Run:

```bash
php bin/create-admin-permission
```

## DI Configuration
The package already contains default configuration in:

- `vendor/drago-ex/permission/src/Drago/Permission/conf.neon`

The bundled config already contains:

- `permissionFactory` service registration
- automatic `search` registration for `*Permission` classes in `%appDir%/UI`

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
		class: App\UI\Sign\SignPermission
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
- not logged in redirect to Sign:in
- logged in but forbidden HTTP 403

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
		// these signals only read data â†’ checked as "{component}-read"
		return ['sort', 'page'];
	}

	protected function readOnlyReceivers(): array
	{
		// any signal from a receiver containing "Grid" checked as "{component}-read"
		return ['Grid'];
	}
}
```

With this configuration the ACL check for `articleGrid:sort` resolves to `articleGrid-read`,
while `articleGrid:delete` resolves to `articleGrid-write`.
