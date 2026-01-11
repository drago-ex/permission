## Drago Permission
Lightweight ACL and role management

The package provides a central ACL factory, modular permission registration per module,
and automatic authorization checks in presenters.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://raw.githubusercontent.com/drago-ex/permission/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fpermission.svg)](https://badge.fury.io/ph/drago-ex%2Fpermissionr)
[![Coding Style](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml)
[![Coverage Status](https://coveralls.io/repos/github/drago-ex/permission/badge.svg?branch=main)](https://coveralls.io/github/drago-ex/permission?branch=main)

## Requirements
- PHP >= 8.3
- Nette Framework
- Composer

## Installation
```
composer require drago-ex/permission
```

## Features
- Central ACL creation
- Modular permission providers per module
- Default roles: guest, member, admin
- Automatic presenter authorization
- Action and signal based privileges

## Roles
Default roles:

- guest
- member (inherits from guest)
- admin (inherits from member)

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


## DI Configuration
Permission factory:
```neon
services:
	permissionFactory:
		class: App\UI\PermissionFactory
		arguments: [tagged(PermisionTag)]

	- @permissionFactory::create
```

Module provider:
```neon
services:
	signPermission:
		class: App\UI\Backend\Sign\SignPermission
		tags: [PermisionTag]
```

## Presenter Authorization
Authorization is handled by the Authorization trait.

- runs automatically on presenter startup
- checks ACL using presenter name and action or signal

Unauthorized access:
- not logged in → redirect to Sign:in
- logged in but forbidden → HTTP 403
