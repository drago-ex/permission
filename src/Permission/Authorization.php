<?php

declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Presenter;
use Nette\InvalidStateException;
use Nette\Security\User;


trait Authorization
{
	/**
	 * Registers an authorization check executed on presenter startup.
	 * Redirects unauthenticated users to login, throws 403 for unauthorized access.
	 */
	public function injectAuthorization(Presenter $presenter, User $user): void
	{
		$presenter->onStartup[] = function () use ($presenter, $user) {
			$resource = $this->resolveAclResource($presenter);

			if (!$this->isAclAllowed($user, (string) $presenter->getName(), $resource)) {
				if (!$user->isLoggedIn()) {
					$presenter->flashMessage('You must be logged in.');
					$presenter->redirect($this->signInDestination(), [
						'backlink' => $presenter->storeRequest(),
					]);
				} else {
					$presenter->error('Forbidden', 403);
				}
			}
		};
	}


	/** Presenter the guests are redirected to. */
	protected function signInDestination(): string
	{
		return ':Sign:in';
	}


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
			return $this->isReadOnlySignal($receiver, $name)
				? "$group-read"
				: "$group-write";
		}

		return $name;
	}


	/**
	 * Decides whether a component signal only reads data. By default by readOnlySignals()
	 * and readOnlyReceivers(); override it to ask a component, e.g. DataGrid::isReadOnlySignal().
	 */
	protected function isReadOnlySignal(string $receiver, string $name): bool
	{
		foreach ($this->readOnlyReceivers() as $mask) {
			if (str_contains($receiver, $mask)) {
				return true;
			}
		}

		return in_array($name, $this->readOnlySignals(), true);
	}


	/**
	 * A presenter without a registered ACL resource is denied, instead of failing with an exception.
	 */
	private function isAclAllowed(User $user, string $resource, string $privilege): bool
	{
		try {
			return $user->isAllowed($resource, $privilege);
		} catch (InvalidStateException) {
			return false;
		}
	}
}
