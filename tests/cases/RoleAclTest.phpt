<?php declare(strict_types=1);

/**
 * Test: role.acl.neon
 */

use Drago\Permission\Role;
use Nette\Bootstrap\Configurator;
use Nette\Security\Authorizator;
use Nette\Security\Permission;
use Tester\Assert;
use Tester\FileMock;

require __DIR__ . '/../bootstrap.php';


/** Builds the ACL from role.acl.neon and a package ACL, as a project merges them. */
function createAcl(string $packageAcl): Permission
{
	$temp = __DIR__ . '/../tmp/' . getmypid();
	@mkdir($temp, 0o777, true); // @ the directory may already exist

	$configurator = new Configurator;
	$configurator->setTempDirectory($temp);
	$configurator->addConfig(__DIR__ . '/../../resources/app/Core/Security/role.acl.neon');
	$configurator->addConfig(FileMock::create($packageAcl, 'neon'));

	$acl = $configurator->createContainer()->getByType(Authorizator::class);
	assert($acl instanceof Permission);
	return $acl;
}


$acl = createAcl(<<<'NEON'
	security:
		resources:
			Backend:Blog:
			Front:Blog:

		rules:
			allow:
				- [guest, Front:Blog]
	NEON);


test('Base roles inherit guest → user → admin', function () use ($acl) {
	Assert::same([Role::Guest, Role::User, Role::Admin], $acl->getRoles());
	Assert::true($acl->roleInheritsFrom(Role::User, Role::Guest));
	Assert::true($acl->roleInheritsFrom(Role::Admin, Role::User));
});


test('Administrator is allowed every resource and privilege', function () use ($acl) {
	Assert::true($acl->isAllowed(Role::Admin, 'Backend:Blog', 'default'));
	Assert::true($acl->isAllowed(Role::Admin, 'Backend:Blog', 'edit'));
	Assert::true($acl->isAllowed(Role::Admin, 'Front:Blog', 'default'));
});


test('Package rules add access for other roles', function () use ($acl) {
	Assert::true($acl->isAllowed(Role::Guest, 'Front:Blog', 'default'));
	Assert::true($acl->isAllowed(Role::User, 'Front:Blog', 'default'));
	Assert::false($acl->isAllowed(Role::Guest, 'Backend:Blog', 'default'));
	Assert::false($acl->isAllowed(Role::User, 'Backend:Blog', 'default'));
});
