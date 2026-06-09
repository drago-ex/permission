<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Security\Permission;


/** Factory for creating a Nette Component (ACL) instance with default roles and providers. */
class PermissionFactory
{
	/** @var iterable<int, Provider> */
	private iterable $initializers;


	/** @param iterable<int, Provider> $initializers */
	public function __construct(iterable $initializers)
	{
		$this->initializers = $initializers;
	}


	/** Creates and returns a Component object with default roles and registered providers. */
	public function create(): Permission
	{
		$acl = new Permission;
		$acl->addRole(Role::RoleGuest);
		$acl->addRole(Role::RoleUser, Role::RoleGuest);
		$acl->addRole(Role::RoleAdmin, Role::RoleUser);

		foreach ($this->initializers as $initializer) {
			$initializer->register($acl);
		}

		return $acl;
	}
}
