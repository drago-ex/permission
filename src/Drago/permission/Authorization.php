<?php

namespace App\UI;

use Nette\Application\UI\Presenter;
use Nette\Security\User;


trait Authorization
{
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
	 * Resolves ACL resource name from the current presenter action or signal.
	 */
	protected function resolveAclResource(Presenter $presenter): string
	{
		$signal = $presenter->getSignal();

		if ($signal === null) {
			return $presenter->getAction();
		}

		[$receiver, $name] = $signal;
		return $receiver ? "$receiver-$name" : $name;
	}
}
