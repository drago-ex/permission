<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Presenter;
use Nette\Security\User;


trait Authorization
{
	/** @return list<string> */
	protected function readOnlySignals(): array
	{
		return [];
	}


	/** @return list<string> */
	protected function readOnlyReceivers(): array
	{
		return [];
	}


	/**
	 * Registers an authorization check executed on presenter startup.
	 * Redirects unauthenticated users to login, throws 403 for unauthorized access.
	 */
	public function injectAuthorization(Presenter $presenter, User $user): void
	{
		$presenter->onStartup[] = function () use ($presenter, $user) {
			$resource = $this->resolveAclResource($presenter);

			if (!$user->isAllowed($presenter->getName(), $resource)) {
				if (!$user->isLoggedIn()) {
					$presenter->flashMessage('You must be logged in.');
					$presenter->redirect(':Sign:in', [
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
	 * Page load -> "{action}-read", signals -> "{component}-read/write".
	 */
	protected function resolveAclResource(Presenter $presenter): string
	{
		$signal = $presenter->getSignal();

		if ($signal === null) {
			return $presenter->getAction() . '-read';
		}

		[$receiver, $name] = $signal;

		if ($receiver !== '') {
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
