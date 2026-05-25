<?php

declare(strict_types=1);

use Drago\Permission\PermissionFactory;
use Drago\Permission\Provider;
use Drago\Permission\Role;
use Nette\Security\Permission;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

final class TestProvider implements Provider
{
	public bool $called = false;


	public function register(Permission $acl): void
	{
		$this->called = true;
		$acl->addResource('Backend:Sign');
		$acl->allow(Role::RoleGuest, 'Backend:Sign');
	}
}

$provider = new TestProvider;
$factory = new PermissionFactory([$provider]);
$acl = $factory->create();

Assert::true($acl->hasRole(Role::RoleGuest));
Assert::true($acl->hasRole(Role::RoleUser));
Assert::true($acl->hasRole(Role::RoleAdmin));
Assert::true($provider->called);
Assert::true($acl->hasResource('Backend:Sign'));
Assert::true($acl->isAllowed(Role::RoleGuest, 'Backend:Sign'));
Assert::true($acl->isAllowed(Role::RoleUser, 'Backend:Sign'));
Assert::true($acl->isAllowed(Role::RoleAdmin, 'Backend:Sign'));
