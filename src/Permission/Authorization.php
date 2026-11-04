<?php declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;


/**
 * Checks the ACL of a presenter, with the presenter name as the resource and the operation as the privilege:
 * the page (its action, e.g. "default") on startup, and a signal right before its handler runs, when the receiving
 * component exists (its name, e.g. "delete", or what its Access attribute sets).
 * @mixin Presenter
 * @phpstan-require-extends Presenter
 */
trait Authorization
{
	/** Registers the check of the page (the action as the privilege) on presenter startup. */
	public function injectAuthorization(): void
	{
		$this->onStartup[] = function (): void {
			$this->checkPrivilege($this->getAction());
		};
	}


	/**
	 * Checks the privilege of the signal before Nette calls its handler.
	 * @throws AbortException
	 * @throws BadRequestException
	 */
	public function processSignal(): void
	{
		$signal = $this->getSignal();
		if ($signal !== null) {
			[$receiver, $name] = $signal;
			$component = $receiver === '' ? $this : $this->getComponent($receiver, throw: false);
			$privilege = $component === null ? $name : Privilege::forSignal($component, $name);
			if ($privilege !== null) {
				$this->checkPrivilege($privilege);
			}
		}

		parent::processSignal();
	}


	/** Presenter the guests are redirected to. */
	protected function signInDestination(): string
	{
		return ':Sign:in';
	}


	/**
	 * Redirects a guest to sign in and gives a signed-in user 403 when the privilege is not allowed.
	 * @throws AbortException
	 * @throws BadRequestException
	 */
	private function checkPrivilege(string $privilege): void
	{
		$user = $this->getUser();
		if (Privilege::isAllowed($user, (string) $this->getName(), $privilege)) {
			return;
		}

		if ($user->isLoggedIn()) {
			$this->error('Forbidden', 403);
		}

		// only a plain GET page is stored for the return: a signal or a form replayed after signing in
		// would pass as a same-origin request (e.g. a delete from a link sent to the user)
		$backlink = $this->getSignal() === null && $this->getRequest()->isMethod('GET')
			? $this->storeRequest()
			: null;

		$this->flashMessage('You must be logged in.');
		$this->redirect($this->signInDestination(), ['backlink' => $backlink]);
	}
}
