# Drago Permission

Lightweight ACL and role management.
The package provides the base roles, ACL written in neon next to the code it protects,
and automatic authorization checks in presenters.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/drago-ex/permission/blob/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fpermission.svg)](https://badge.fury.io/ph/drago-ex%2Fpermission)
[![Tests](https://github.com/drago-ex/permission/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/tests.yml)
[![Coding Style](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml)

## Requirements

- PHP >= 8.3, < 9
- Nette Framework
- Composer

## Installation

```bash
composer require drago-ex/permission
```

## Features

- Default roles: guest, user, admin
- ACL in neon files, one per module
- Automatic presenter authorization
- Privileges named by the operation (`default`, `edit`, `delete`), set by the `Access` attribute where needed
- Latte function `isAllowed()` for menus and links
- Role switch panel for Tracy

## Roles

The package ships `role.acl.neon` with the default roles; the administrator is allowed everything:

- guest
- user (inherits from guest)
- admin (inherits from user)

[drago-ex/project-tools](https://github.com/drago-ex/project-tools) copies it into the project as
`app/Core/Security/role.acl.neon`, where Nette loads it with the other configuration files of `app/`. A later update
keeps your changes. Without project-tools, copy `resources/app/Core/Security/role.acl.neon` of this package there.

The role names are also available as constants of `Drago\Permission\Role`.

## ACL of Modules

The rules of a presenter are in `acl.neon` in its directory, next to its `conf.neon`, in the `security` section
of Nette. Nette merges the files of all modules into one `Nette\Security\Permission` service, so no PHP code is needed.

The resource is the presenter name. A rule is needed only for roles other than the administrator, for example
public pages for guests:

```neon
# app/Presentation/Backend/Blog/acl.neon
security:
	resources:
		Backend:Blog:
```

```neon
# app/Presentation/Front/Blog/acl.neon
security:
	resources:
		Front:Blog:

	rules:
		allow:
			- [guest, Front:Blog]
```

A new presenter needs its resource (and a guest rule when it is public) in the `acl.neon` next to it.
A presenter without a resource is denied, see below.

## Presenter Authorization

Authorization is handled by the `Authorization` trait. Include it in the base presenter:

```php
use Drago\Permission\Authorization;

class BasePresenter extends Nette\Application\UI\Presenter
{
	use Authorization;
}
```

As in the [Nette ACL](https://doc.nette.org/en/security/acl), the resource is what is protected (the presenter)
and the privilege is the operation:

| When                                    | Privilege                                                |
|-----------------------------------------|----------------------------------------------------------|
| on startup, every request               | the action: `default`, `comments`, `edit`                |
| a signal, right before its handler runs | the signal: `delete!` → `delete`, `approve!` → `approve` |

A signal also needs its page: deleting on `Blog:default` needs `default` and `delete`. The rules then read
like the application:

```neon
security:
	roles:
		editor: [user]
	rules:
		allow:
			- [editor, Backend:Blog, [default, comments, edit]]   # may edit, not delete
```

Unauthorized access:
- not logged in: redirect to `:Sign:in` with a backlink (override `signInDestination()` to change the target); only a
  plain GET page is stored, a signal or a form is not replayed after signing in
- logged in but forbidden: HTTP 403
- a presenter without a registered ACL resource is denied the same way, so a forgotten resource never opens a page

### The Access attribute

A signal without the attribute needs the privilege named like it, a form the privilege named like the form
(the `delete` form → `delete`). The `Access` attribute changes that on the handler, or on the factory
`createComponent<Name>()` or the class of a component for all its signals and subcomponents (e.g. its form):

```php
use Drago\Permission\Access;

class ArticleControl extends Nette\Application\UI\Control
{
	public function handleDelete(int $id): void      // delete
	{
	}


	#[Access('edit')]
	public function handleRemoveImage(int $id): void // edit, a part of editing
	{
	}


	#[Access]
	public function handlePreview(): void            // the page is enough
	{
	}


	#[Access('edit')]
	protected function createComponentArticle(): Form // edit, its submit
	{
	}
}
```

`#[Access(parameter: 'name')]` takes the privilege from a signal parameter. The signal is checked when its component
already exists, so the attribute of the real class decides, whatever the component is named.
[drago-ex/datagrid](https://github.com/drago-ex/datagrid) uses it this way: sorting, filters, paging and the page size
need only the page, and a row action needs the privilege named like the action (`addActionEdit()` → `edit`,
`addActionDelete()` → `delete`).

The attribute needs nothing at runtime: PHP ignores an attribute whose class is not installed, so any Nette component
or package can mark its signals and still work without this package.

## Buttons

The `AccessCheck` trait lets a component show its buttons only to those who may use them. `isAllowed()` asks for
the privilege on the presenter of the component, the same one the `Authorization` trait checks for the signal:

```php
use Drago\Permission\AccessCheck;

class ArticleControl extends Nette\Application\UI\Control
{
	use AccessCheck;
}
```

```php
$grid->addActionDelete(fn(int $id) => $this->handleDelete($id))
	->setVisible($this->isAllowed('delete'));
```

```latte
<a n:if="$control->isAllowed('add')" n:href="add!" class="btn btn-primary btn-sm">Add</a>
```

Hiding a button is only for comfort; the signal itself is still checked by the `Authorization` trait.

## Checking Other Places

For a menu item or a link to another presenter, `Privilege::isAllowed()` is true when the user has at least one
of the privileges on the resource. A resource or role the ACL does not know is denied instead of throwing:

```php
use Drago\Permission\Privilege;

Privilege::isAllowed($this->getUser(), 'Backend:Permission', 'default', 'users');
```

In templates, register the Latte extension (requires `latte/latte`):

```neon
latte:
	extensions:
		- Drago\Permission\Latte\PermissionExtension
```

and use the function `isAllowed()` with the same arguments:

```latte
<li n:if="isAllowed('Backend:Permission', 'default', 'users')">
	<a n:href=":Backend:Permission:">Permissions</a>
</li>
```

## Privileges of a Presenter

`PrivilegeCatalog` reads the privileges of a presenter from its code by the same rules: the pages (`action*()`,
`render*()` and the templates next to it) and the signals and forms of the presenter and its components. Signals that
need only the page are left out. An administration of permissions offers them without any list kept by hand:

```php
$catalog = new Drago\Permission\PrivilegeCatalog($presenterFactory);
$catalog->getPrivileges('Backend:Blog'); // ['default', 'categories', 'delete', 'edit', …]
```

## Role Switch in Tracy

While developing, the Tracy bar can switch the roles of the signed-in user, so you can try the application as
another role without a test account. Register the panel (requires `tracy/tracy`):

```neon
tracy:
	bar:
		- Drago\Permission\Tracy\RoleSwitchPanel
```

The tab shows the current roles (red while they are switched). The panel lists the roles of the ACL, from neon
as well as from the database; **My roles** brings the real ones back, and signing out forgets the switch.
Nette adds bar panels only in debug mode, so the panel never runs in production.

---

## Optional add-ons

The package works without them. Set them up by hand as described below when the project needs them.

### Related Package: Dynamic Role Management

For dynamic role and access management, use:

- `drago-ex/project-permission`: https://github.com/drago-ex/project-permission

This package is built on top of `drago-ex/permission` and provides:

- role creation
- assigning roles to users
- allowing or denying access per role
