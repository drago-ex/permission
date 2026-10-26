<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\InvalidStateException;
use Nette\Security\User;


/**
 * How the Authorization and WriteAccess traits name and check privileges, kept in one place
 * so that a write button is shown exactly when its signal is allowed.
 */
final class Privilege
{
	/**
	 * The privilege of a component's signals: the first part of the receiver ("articles-grid" → "articles")
	 * with "-read" or "-write".
	 */
	public static function forComponent(string $receiver, bool $write): string
	{
		return explode('-', $receiver)[0] . ($write ? '-write' : '-read');
	}


	/**
	 * A presenter without a registered ACL resource is denied, instead of failing with an exception.
	 */
	public static function isAllowed(User $user, string $resource, string $privilege): bool
	{
		try {
			return $user->isAllowed($resource, $privilege);
		} catch (InvalidStateException) {
			return false;
		}
	}
}
