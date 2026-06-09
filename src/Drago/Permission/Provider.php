<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Security\Permission;


/** Registers roles and privileges on a Component (ACL) instance. */
interface Provider
{
	public function register(Permission $acl): void;
}
