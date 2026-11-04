<?php declare(strict_types=1);

use Drago\Permission\AccessCheck;
use Drago\Permission\Authorization;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;


/** Let PHPStan analyse the traits, which no class in the package uses. */
class AuthorizationStub extends Presenter
{
	use Authorization;
}


class AccessCheckStub extends Control
{
	use AccessCheck;
}
