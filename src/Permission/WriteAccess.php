<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Control;
use Nette\InvalidStateException;


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
		$component = explode('-', $this->getUniqueId())[0];

		try {
			return $presenter->getUser()->isAllowed((string) $presenter->getName(), "$component-write");
		} catch (InvalidStateException) {
			return false; // a presenter without an ACL resource is denied, as by the Authorization trait
		}
	}
}
