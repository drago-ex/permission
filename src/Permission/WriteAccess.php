<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Control;


/**
 * Lets a component show its write buttons (add, edit, delete) only to those who may use them:
 * by the "{component}-write" privilege the Authorization trait requires for the component's signals.
 * @phpstan-require-extends Control
 */
trait WriteAccess
{
	public function isWriteAllowed(): bool
	{
		$presenter = $this->getPresenter();
		return Privilege::isAllowed(
			$presenter->getUser(),
			(string) $presenter->getName(),
			Privilege::forComponent($this->getUniqueId(), write: true),
		);
	}
}
