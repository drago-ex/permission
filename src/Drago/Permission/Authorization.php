<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Presenter;
use Nette\Security\User;


trait Authorization
{
	/**
	 * Override in presenter to define which signal names resolve to read privilege.
	 */
	protected function readOnlySignals(): array
	{
		return [];
	}


	/**
	 * Override in presenter to define which receiver substrings resolve to read privilege.
	 * Any signal whose receiver contains one of these strings → "{component}-read".
	 */
	protected function readOnlyReceivers(): array
	{
		return [];
	}


	/**
	 * Registers an authorization check executed on presenter startup.
	 *
	 * Verifies whether the current user is allowed to access the current
	 * presenter action or signal. If the user is not authenticated,
	 * they are redirected to the login page. If authenticated but not
	 * authorized, a 403 error is thrown.
	 */
	public function injectAuthorization(Presenter $presenter, User $user): void
	{
		$presenter->onStartup[] = function () use ($presenter, $user) {
			$resource = $this->resolveAclResource($presenter);

			if (!$user->isAllowed($presenter->getName(), $resource)) {
				if (!$user->isLoggedIn()) {
					$presenter->flashMessage('You must be logged in.');
					$presenter->redirect('Sign:in', [
						'backlink' => $presenter->storeRequest(),
					]);
				} else {
					$presenter->error('Forbidden', 403);
				}
			}
		};
	}


	/**
	 * Resolves ACL privilege from the current presenter action or signal.
	 *
	 *   - page load (no signal)               → "{action}-read"
	 *   - read-only receivers (e.g. dataGrid) → "{component}-read"
	 *   - read-only signals (sort, page...)   → "{component}-read"
	 *   - write signals (submit, delete...)   → "{component}-write"
	 */
	protected function resolveAclResource(Presenter $presenter): string
	{
		$signal = $presenter->getSignal();

		if ($signal === null) {
			return $presenter->getAction() . '-read';
		}

		[$receiver, $name] = $signal;

		if ($receiver) {
			$group = explode('-', $receiver)[0];
			$isReadOnlyReceiver = (bool) array_filter(
				$this->readOnlyReceivers(),
				fn(string $r) => str_contains($receiver, $r),
			);

			if ($isReadOnlyReceiver || in_array($name, $this->readOnlySignals(), true)) {
				return "$group-read";
			}

			return "$group-write";
		}

		return $name;
	}
}
