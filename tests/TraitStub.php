<?php

declare(strict_types=1);

use Drago\Permission\Authorization;


/** Lets PHPStan analyse the Authorization trait, which no class in the package uses. */
class TraitStub
{
	use Authorization;
}
