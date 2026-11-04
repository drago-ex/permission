<?php declare(strict_types=1);

namespace Drago\Permission\Latte;

use Drago\Permission\Privilege;
use Latte\Extension;
use Nette\Security\User;


/**
 * Adds the Latte function isAllowed(resource, privilege, ...), true when the user has at least one
 * of the privileges, e.g. for a menu item or a link to another presenter: n:if="isAllowed('Backend:Blog', 'default')".
 * Register it in the `latte: extensions:` section.
 */
final class PermissionExtension extends Extension
{
	public function __construct(
		private readonly User $user,
	) {
	}


	public function getFunctions(): array
	{
		return [
			'isAllowed' => fn(string $resource, string $privilege, string ...$privileges): bool
				=> Privilege::isAllowed($this->user, $resource, $privilege, ...$privileges),
		];
	}
}
