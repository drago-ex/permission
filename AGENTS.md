# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/permission`
- **Type**: library
- **Purpose**: Lightweight ACL and role management.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: nette/security (^3.1), nette/application (^3.2), nette/http (^3.3); tracy/tracy (role switch panel) and latte/latte (isAllowed() function) are suggested
- **License**: MIT

## Repository Layout
- `src/`: package source code.
- `tests/`: automated tests.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

## Key Parts
- `resources/app/Core/Security/role.acl.neon` (copied to `app/Core/Security/` by drago-install, `extra.drago-tools`; user 2026-11-04, not `local/`: roles are application code in git and in the Docker image) holds the base roles (guest → user → admin, the same names as the constants `Role::Guest`, `Role::User`, `Role::Admin`; renamed from `RoleGuest`… in 2.0) and `allow: [admin]`; Nette loads it from `app/` like the other neons, no `includes` from vendor. Packages ship an `acl.neon` in each presenter directory next to its `conf.neon` (resources = presenter names, rules only for non-admin access; user, 2026-11-03: one name, the directory tells whose it is) and Nette `SecurityExtension` merges all files into `security.authorizator` (`Permission`). `RoleAclTest.phpt` compiles it with a package ACL through `Nette\Bootstrap\Configurator`. There is no PHP factory, provider or generator (removed in the ACL-in-neon major).
- Privileges follow the Nette ACL convention (user, 2026-11-03: "anyone who knows Nette ACL understands it"): the resource is the presenter name, the privilege the operation. `Authorization` trait (presenters only) checks the action (`default`, `comments`, `edit`) on startup (`injectAuthorization()` adds an `onStartup` handler) and every signal in its override of `processSignal()`, right before `parent::processSignal()` calls the handler: Nette runs `processSignal()` after `action*()`, so the receiving component exists and its real class decides (no guessing from names at startup; the former `-read`/`-write` suffixes, `forComponent()`, `ReadAccess`, `readOnlySignals()`/`readOnlyReceivers()`/`isReadOnlySignal()` and datagrid's `DataGrid::isReadOnlySignal()` were removed in the 2.0 rework). The privilege of a signal is `Privilege::forSignal()`: the `Access` attribute of the handler, else of the class of the receiver or a parent below the presenter (the receiver may be a Form, so it takes `IComponent`); without it the signal name (`delete!` → `delete`); a missing receiver → the signal name. A presenter without a registered resource is denied. Guests are redirected to `signInDestination()` (`:Sign:in`), with a backlink only for a plain GET page.
- `Access` attribute (method or class, `final readonly`): `#[Access('edit')]` sets the privilege, `#[Access]` = the page is enough (null privilege), `#[Access(parameter: 'name')]` = the value of that signal parameter (`getParameter()`, falls back to the signal name). PHP ignores attributes whose class is missing, so other packages (datagrid: `handleSort`, `FilterTextControl`, `PaginatorControl`, `PageSizeControl` with `#[Access]`, `handleAction` with `parameter: 'name'`) use it with permission only in require-dev/suggest.
- Forms: a form receiver without `Access` needs the privilege named like the form (`delete` form → `delete`), because every form signal is `submit`. `Access` is also read from the factory `createComponent<Name>()` of a component (covers all its signals and subcomponents). Order in `Privilege::forSignal()`: handler attribute, then for the receiver and each parent below the presenter its factory attribute, then its class attribute (with parent classes).
- `PrivilegeCatalog` (needs `IPresenterFactory`) lists a presenter's privileges for project-permission by the same rules as runtime: pages from public `action*`/`render*` and `*.latte` next to the presenter (not `@`), public `handle*` (Nette's protected `handleInvalidLink` excluded), forms of `createComponent*` returning a Form (by name or attribute), components recursively up to 3 levels; `#[Access]` (page) and `parameter:` are skipped. Tested with `tests/fixtures/Blog/BlogPresenter.php` (+ templates). Caching belongs to the caller (project-permission, `Cache::Files` of the presenter files).
- `src/Permission/Translate/cs.neon` (`extra.drago-translator`) translates the flash "You must be logged in." of `Authorization`; layouts print flashes with `{_$flash->message}`.
- `Latte\PermissionExtension` (registered by the project in `latte: extensions:`, `User` autowired; latte/latte only require-dev + suggest) adds the Latte function `isAllowed(resource, privilege, ...)` = `Privilege::isAllowed()`, for menus and links in layouts (idea taken from ipub-security's `n:ifAllowed`, 2026-11-03; a function instead of a tag, so `n:if`/`{if}` cover it).
- `AccessCheck` trait (was `WriteAccess`): `isAllowed(privilege, ...)` in a component asks for the privilege on its presenter (unknown resource → false), for buttons: `->setVisible($this->isAllowed('delete'))`, `n:if="$control->isAllowed('add')"` (no `$writeAllowed` template variable). Both traits check only through `Privilege`, so a button and its signal cannot drift apart. `tests/TraitStub.php` (classes `AuthorizationStub`, `AccessCheckStub`: global test class names must be unique in the workspace, PhpStorm merges duplicates like `TraitStub`) lets PHPStan analyse both traits.
- `Tracy\RoleSwitchPanel` (registered by the project in `tracy: bar:`, so Nette creates it only in debug mode) adds an `Application::onStartup` handler: a POSTed panel form (session token, only roles of the `Permission` authorizator) redirects with 303, otherwise the chosen roles are set on the `SimpleIdentity` on every request, because identity handlers such as project-auth's `wakeupIdentity()` load the real roles again. The logic is in `Tracy\RoleSwitch` (session section: user id, chosen and original roles, token; forgotten for another user or when signed out), tested without Tracy in `RoleSwitchTest.phpt`. Works with the ACL from neon and with project-permission's database roles.

## Dependencies

Composer constraints describe the package contract. Before changing integration behavior, inspect the source and the actual call sites in this package; do not infer runtime usage solely from dependency declarations.
Development dependencies include nette/tester (^2.5), nette/bootstrap (^3.1), phpstan/phpstan (^2.0), phpstan/phpstan-nette (^2.0), tracy/tracy (^2.10), latte/latte (^3.0), nette/forms (^3.2, form signals in tests).

## Essential Commands

```bash
composer tester                 # tester tests/cases -s
composer phpstan                # phpstan analyse
```

## Conventions & Rules

- Preserve backward compatibility unless the task explicitly permits a breaking change. Check callers and public signatures before changing behavior.
- Follow the existing PHP style: strict types, explicit parameter and return types, tabs for indentation, and single-quoted strings where practical. Match nearby code when a convention is unclear.
- Keep package-specific configuration, resources, and migrations in their established directories. Do not edit generated or consuming-project files without confirming they are part of this repository’s intended output.
- Add or update focused tests for behavioral changes when this package has a runnable test setup. Do not invent test commands; use only the scripts and configuration present here.
- Update README examples and Composer metadata when a public API or supported requirement changes.
