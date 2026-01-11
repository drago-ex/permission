<?php

namespace App\UI;

use Nette\Security\Permission;


/**
 * Registers roles and privileges on a Permission (ACL) instance.
 */
interface Provider
{
	public function register(Permission $acl): void;
}
