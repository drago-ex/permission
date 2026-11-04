<?php declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Control;


/**
 * Lets a component show its buttons (add, edit, delete) only to those who may use them: isAllowed('delete')
 * asks for the privilege on its presenter, the same one the Authorization trait checks for the signal.
 * @phpstan-require-extends Control
 */
trait AccessCheck
{
	/** True when the user has at least one of the privileges on the presenter of the component. */
	public function isAllowed(string $privilege, string ...$privileges): bool
	{
		$presenter = $this->getPresenter();
		return Privilege::isAllowed($presenter->getUser(), (string) $presenter->getName(), $privilege, ...$privileges);
	}
}
