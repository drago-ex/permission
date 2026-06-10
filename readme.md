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

final class PermissionProvider implements Provider
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

When you use the shared `PermissionProvider` class name, pass the resource and output directory explicitly.
The class name is intentionally the same in every module and the namespace decides where the provider belongs.

Example for a Sign module:

```bash
vendor/bin/create-permission PermissionProvider App\UI\Backend\Sign Backend:Sign app/UI/Backend/Sign
```

Example for an Admin module:

```bash
vendor/bin/create-permission PermissionProvider App\UI\Backend\Admin Backend:Admin app/UI/Backend/Admin --allow-role=RoleAdmin --allow-with-resource=0
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
vendor/bin/create-permission PermissionProvider App\UI\Backend\Admin Backend:Admin app/UI/Backend/Admin --allow=RoleAdmin --allow=RoleUser,self::Resource,default
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

For one-command generation per module, add a local wrapper script in your app, e.g. `bin/create-admin-permission`.
The class can keep the same `PermissionProvider` name in each module because every module has its own namespace:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/vendor/bin/create-permission';

// 1. Positional arguments with clear description.
$positionalArgs = [
	'className'   => 'PermissionProvider',
	'namespace'   => 'App\\UI\\Backend\\Admin',
	'resource'    => 'Backend:Admin',
	'outputDir'   => 'app/UI/Backend/Admin',
];

// 2. Optional switches (options).
$options = [
	'--allow=RoleAdmin',
	'--allow=RoleUser,self::Resource,default',
];

// We will combine them into one flat field for the shell.
$args = array_merge(array_values($positionalArgs), $options);

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
- automatic `search` registration for `PermissionProvider` classes in `%appDir%/UI`

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
		class: App\UI\Sign\PermissionProvider
		tags: [PermisionTag]
```

For larger applications with many providers, you can use the `search` section to register all matching classes automatically instead of listing each one individually:
```neon
search:
	permissions:
		in: %appDir%/UI
		classes: [PermissionProvider]
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

For component signals, Nette returns a pair from `Presenter::getSignal()`:

```php
[$receiver, $signal] = $presenter->getSignal();
```

The receiver is the component path joined by hyphens. For example, if a presenter has a `roles`
component and that component contains a `dataGrid` component with a `paginator` subcomponent,
Nette can produce a signal like:

```text
roles-dataGrid-paginator:page
```

This is resolved as:

```php
$receiver = 'roles-dataGrid-paginator';
$signal = 'page';
```

The authorization trait uses the first receiver segment as the privilege group:

```php
$group = explode('-', $receiver)[0]; // "roles"
```

So the signal above resolves to:

```text
roles-read
```

when it is configured as read-only, otherwise it resolves to:

```text
roles-write
```

### Read-only signals

Override `readOnlySignals()` to declare signal names that should be treated as read operations.
This checks only the signal name, not the component path.

```php
protected function readOnlySignals(): array
{
	return ['sort', 'page', 'resetFilters', 'setPageSize'];
}
```

Examples:

| Nette signal                      | Receiver                 | Signal          | Resolved privilege |
|-----------------------------------|--------------------------|-----------------|--------------------|
| `roles-dataGrid:sort`             | `roles-dataGrid`         | `sort`          | `roles-read`       |
| `roles-dataGrid-paginator:page`   | `roles-dataGrid-paginator` | `page`        | `roles-read`       |
| `roles-dataGrid:action`           | `roles-dataGrid`         | `action`        | `roles-write`      |

### Read-only receivers

Override `readOnlyReceivers()` to declare component path substrings whose signals should always
resolve to read privilege. This checks the receiver path, not the signal name.

```php
protected function readOnlyReceivers(): array
{
	return ['Grid-filters', 'Grid-paginator', 'Grid-pageSize'];
}
```

Any signal whose receiver name contains one of these strings will be resolved as `{component}-read` regardless of the signal name.

This is useful for nested controls where the signal name is generic, for example a filter form submit:

```text
roles-dataGrid-filters-form:submit
```

The signal name is `submit`, but the receiver contains `Grid-filters`, so it resolves to:

```text
roles-read
```

Be careful with broad receiver masks such as `Grid`. They make every signal from that receiver read-only,
including row actions like:

```text
roles-dataGrid:action
```

Use broad receiver masks only when the whole component is read-only.

### Full example

```php
use Drago\Permission\Authorization;

class ArticlePresenter extends BasePresenter
{
	protected function readOnlySignals(): array
	{
		// these signals only read data checked as "{component}-read"
		return ['sort', 'page', 'resetFilters', 'setPageSize'];
	}

	protected function readOnlyReceivers(): array
	{
		// read-only DataGrid subcomponents with generic signal names
		return ['Grid-filters', 'Grid-paginator', 'Grid-pageSize'];
	}
}
```

With this configuration:

| Nette signal                         | Resolved privilege |
|--------------------------------------|--------------------|
| `roles-dataGrid:sort`                | `roles-read`       |
| `roles-dataGrid-paginator:page`      | `roles-read`       |
| `roles-dataGrid-filters:resetFilters`| `roles-read`       |
| `roles-dataGrid-filters-form:submit` | `roles-read`       |
| `roles-dataGrid-pageSize:setPageSize`| `roles-read`       |
| `roles-dataGrid:action`              | `roles-write`      |

If you are not sure what Nette produces for a specific request, temporarily inspect:

```php
bdump($this->getSignal());
```

or log the value inside `resolveAclResource()`.
