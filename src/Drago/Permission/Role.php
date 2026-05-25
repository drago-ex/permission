<?php

declare(strict_types=1);

namespace Drago\Permission;


/** Defines constants for default application roles. */
class Role
{
	public const string
		RoleGuest = 'guest',
		RoleUser = 'user',
		RoleAdmin = 'admin';
}
