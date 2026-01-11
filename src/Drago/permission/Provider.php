<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Security\Permission;


/**
 * Registers roles and privileges on a Permission (ACL) instance.
 */
interface Provider
{
	public function register(Permission $acl): void;
}
