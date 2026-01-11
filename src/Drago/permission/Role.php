<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Permission;


/**
 * Defines constants for application roles.
 */
class Role
{
	public const string
		RoleGuest = 'guest',
		RoleMember = 'member',
		RoleAdmin = 'admin';
}
