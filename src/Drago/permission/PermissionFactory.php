<?php

namespace App\UI;

use LogicException;
use Nette\Security\Permission;


/**
 * Factory for creating a Nette\Security\Permission (ACL) instance.
 *
 * Applies default roles and runs all registered Provider initializers.
 */
final class PermissionFactory
{
	/** @var iterable<Provider> */
	private iterable $initializers;

	public function __construct(iterable $initializers)
	{
		foreach ($initializers as $initializer) {
			if (!$initializer instanceof Provider) {
				throw new LogicException(sprintf(
					'%s must implement Provider',
					get_class($initializer)
				));
			}
		}
		$this->initializers = $initializers;
	}


	/**
	 * Creates and returns a Permission object with default roles and registered providers.
	 */
	public function create(): Permission
	{
		$acl = new Permission;
		$acl->addRole(Role::RoleGuest);
		$acl->addRole(Role::RoleMember, Role::RoleGuest);
		$acl->addRole(Role::RoleAdmin, Role::RoleMember);

		foreach ($this->initializers as $initializer) {
			$initializer->register($acl);
		}

		return $acl;
	}
}
