<?php declare(strict_types=1);

namespace Drago\Permission;


/** Names of the base roles of role.acl.neon. */
final class Role
{
	public const string Guest = 'guest',
		User = 'user',
		Admin = 'admin';
}
