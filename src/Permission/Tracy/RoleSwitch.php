<?php

declare(strict_types=1);

namespace Drago\Permission\Tracy;

use Nette\Http\IRequest;
use Nette\Http\SessionSection;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;


/**
 * Lets a developer try the application with other roles of the ACL.
 * The chosen roles live in the session and replace the roles of the signed-in user on every request,
 * so they survive identity handlers that load the roles again; reset() brings back the real ones.
 */
final class RoleSwitch
{
	public const string Field = '_roleSwitch';


	public function __construct(
		private readonly User $user,
		private readonly SessionSection $section,
	) {
	}


	/** Replaces the roles of the signed-in user by the chosen ones; forgets them once that user is gone. */
	public function apply(): void
	{
		if ($this->section->get('roles') === null) {
			return;
		}

		$identity = $this->getIdentity();
		if ($identity === null || $identity->getId() !== $this->section->get('user')) {
			$this->section->remove();
			return;
		}

		$identity->setRoles($this->section->get('roles'));
	}


	/**
	 * Handles a submitted panel form; returns true when the request should be redirected.
	 */
	public function handle(IRequest $request): bool
	{
		$action = $request->getPost(self::Field);
		if (!$request->isMethod('POST') || !is_string($action)) {
			return false;
		}

		if ($request->getPost('_token') !== $this->getToken() || $this->getIdentity() === null) {
			return true;
		}

		if ($action === 'reset') {
			$this->reset();
		} else {
			$posted = $request->getPost('roles');
			$this->switch(array_values(array_intersect($this->getAvailableRoles(), is_array($posted) ? $posted : [])));
		}

		return true;
	}


	/** @param list<string> $roles */
	public function switch(array $roles): void
	{
		$identity = $this->getIdentity();
		if ($identity === null) {
			return;
		}

		if ($this->section->get('roles') === null) {
			$this->section->set('user', $identity->getId());
			$this->section->set('original', $identity->getRoles());
		}

		$this->section->set('roles', $roles);
		$identity->setRoles($roles);
	}


	public function reset(): void
	{
		$identity = $this->getIdentity();
		if ($identity !== null && $this->section->get('roles') !== null) {
			$identity->setRoles($this->section->get('original'));
		}

		$this->section->remove();
	}


	public function isSwitched(): bool
	{
		return $this->section->get('roles') !== null;
	}


	public function isSignedIn(): bool
	{
		return $this->getIdentity() !== null;
	}


	/** @return list<string> */
	public function getCurrentRoles(): array
	{
		return array_map(strval(...), $this->user->getRoles());
	}


	/** @return list<string> roles of the ACL, empty when the authorizator is not a Permission */
	public function getAvailableRoles(): array
	{
		$authorizator = $this->user->getAuthorizatorIfExists();
		return $authorizator instanceof Permission ? $authorizator->getRoles() : [];
	}


	public function getToken(): string
	{
		$token = $this->section->get('token');
		if (!is_string($token)) {
			$token = bin2hex(random_bytes(16));
			$this->section->set('token', $token);
		}

		return $token;
	}


	private function getIdentity(): ?SimpleIdentity
	{
		$identity = $this->user->isLoggedIn() ? $this->user->getIdentity() : null;
		return $identity instanceof SimpleIdentity ? $identity : null;
	}
}
