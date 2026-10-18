# Agent Guide

Use this file as package-specific guidance when working in this repository. Keep changes aligned with the existing public API, project conventions, and supported PHP range.

## Package Overview
- **Package**: `drago-ex/permission`
- **Type**: library
- **Purpose**: Lightweight ACL and role management.
- **PHP**: >=8.3 <9
- **Runtime dependencies**: nette/robot-loader (^4.1), nette/security (^3.1), nette/application (^3.2), nette/di (^3.1)
- **License**: MIT

## Repository Layout
- `bin/`: command-line entry points.
- `src/`: package source code.
- `tests/`: automated tests.
- `.github/`: CI workflows; keep their PHP versions and commands consistent with Composer and the package scripts.
- `composer.json`: package metadata, dependency constraints, autoloading, and available Composer scripts.

## Key Parts
- `PermissionFactory` builds the ACL with the guest, user and admin roles and runs tagged `Provider` services.
- `Authorization` trait checks the presenter name with the resolved privilege on startup; a presenter without a registered resource is denied. Guests are redirected to `signInDestination()` (`:Sign:in`).
- `src/Permission/conf.neon` is not loaded automatically; its `search` section needs `nette/robot-loader`, which is therefore a runtime dependency.
- `bin/create-permission` generates providers; it is tested through the CLI in `tests/cases/CreatePermissionCommandTest.phpt`.
- `create-permission --packages` generates the providers that installed packages declare in `extra.drago-permission` (a PHP file returning definitions whose keys mirror the CLI options) and writes `.drago-project/permission.json`. It finds packages via `Composer\InstalledVersions`, so it does not depend on `drago-ex/project-tools`. Tests fake the installed packages with `InstalledVersions::reload()` in a generated `vendor/autoload.php`.

## Dependencies

Composer constraints describe the package contract. Before changing integration behavior, inspect the source and the actual call sites in this package; do not infer runtime usage solely from dependency declarations.
Development dependencies include nette/tester (^2.5), nette/bootstrap (^3.1), phpstan/phpstan (^2.0), phpstan/phpstan-nette (^2.0).

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
