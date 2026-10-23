<?php

declare(strict_types=1);

use Drago\Permission\Authorization;
use Drago\Permission\WriteAccess;
use Nette\Application\UI\Control;


/** Let PHPStan analyse the traits, which no class in the package uses. */
class TraitStub
{
	use Authorization;
}


class WriteAccessStub extends Control
{
	use WriteAccess;
}
