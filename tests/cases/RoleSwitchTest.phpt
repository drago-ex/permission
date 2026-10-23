<?php

/**
 * Test: Drago\Permission\Tracy\RoleSwitch
 */

declare(strict_types=1);

use Drago\Permission\Tracy\RoleSwitch;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Http\UrlScript;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


final class RequestStorage implements UserStorage
{
	public function __construct(
		private ?IIdentity $identity,
	) {
	}


	public function saveAuthentication(IIdentity $identity): void
	{
	}


	public function clearAuthentication(bool $clearIdentity): void
	{
	}


	public function getState(): array
	{
		return [$this->identity !== null, $this->identity, null];
	}


	public function setExpiration(?string $expire, bool $clearIdentity): void
	{
	}
}


function acl(): Permission
{
	$acl = new Permission;
	$acl->addRole('guest');
	$acl->addRole('moderator', 'guest');
	$acl->addRole('admin', 'moderator');
	return $acl;
}


/** A user of one request; the identity is loaded again with its real roles, as identity handlers do. */
function signedIn(int $id, array $roles = ['admin']): User
{
	return new User(storage: new RequestStorage(new SimpleIdentity($id, $roles)), authorizator: acl());
}


function section(): SessionSection
{
	static $count = 0;
	$session = new Session(new Request(new UrlScript('http://localhost/')), new Response);
	return $session->getSection('role-switch-' . ++$count);
}


function post(array $data): Request
{
	return new Request(new UrlScript('http://localhost/admin/'), post: $data, method: 'POST');
}


test('Switched roles replace the reloaded roles on the next request', function () {
	$section = section();
	$user = signedIn(1);
	$roleSwitch = new RoleSwitch($user, $section);
	$roleSwitch->switch(['moderator']);

	Assert::same(['moderator'], $user->getRoles());
	Assert::true($roleSwitch->isSwitched());

	$next = signedIn(1);
	(new RoleSwitch($next, $section))->apply();
	Assert::same(['moderator'], $next->getRoles());
	Assert::false($next->isInRole('admin'));
});


test('Reset brings back the real roles', function () {
	$section = section();
	$user = signedIn(1);
	$roleSwitch = new RoleSwitch($user, $section);
	$roleSwitch->switch(['moderator']);
	$roleSwitch->switch(['guest']);
	Assert::same(['guest'], $user->getRoles());

	$roleSwitch->reset();
	Assert::same(['admin'], $user->getRoles());
	Assert::false($roleSwitch->isSwitched());
});


test('Switched roles are forgotten for another user or after signing out', function () {
	$section = section();
	(new RoleSwitch(signedIn(1), $section))->switch(['moderator']);

	$other = signedIn(2);
	$roleSwitch = new RoleSwitch($other, $section);
	$roleSwitch->apply();
	Assert::same(['admin'], $other->getRoles());
	Assert::false($roleSwitch->isSwitched());

	(new RoleSwitch(signedIn(1), $section))->switch(['moderator']);
	$guest = new User(storage: new RequestStorage(null), authorizator: acl());
	$roleSwitch = new RoleSwitch($guest, $section);
	$roleSwitch->apply();
	Assert::false($roleSwitch->isSwitched());
	Assert::false($roleSwitch->isSignedIn());
});


test('The form needs POST and the token and accepts only roles of the ACL', function () {
	$user = signedIn(1);
	$roleSwitch = new RoleSwitch($user, section());

	Assert::false($roleSwitch->handle(new Request(new UrlScript('http://localhost/?' . RoleSwitch::Field . '=switch'))));
	Assert::false($roleSwitch->handle(post(['roles' => ['guest']])));

	Assert::true($roleSwitch->handle(post([RoleSwitch::Field => 'switch', 'roles' => ['guest'], '_token' => 'wrong'])));
	Assert::false($roleSwitch->isSwitched());

	$token = $roleSwitch->getToken();
	Assert::true($roleSwitch->handle(post([RoleSwitch::Field => 'switch', 'roles' => ['guest', 'root'], '_token' => $token])));
	Assert::same(['guest'], $user->getRoles());

	Assert::true($roleSwitch->handle(post([RoleSwitch::Field => 'reset', '_token' => $token])));
	Assert::same(['admin'], $user->getRoles());
});


test('Roles are offered only from a Permission authorizator', function () {
	Assert::same(['guest', 'moderator', 'admin'], (new RoleSwitch(signedIn(1), section()))->getAvailableRoles());

	$user = new User(storage: new RequestStorage(new SimpleIdentity(1, ['admin'])));
	Assert::same([], (new RoleSwitch($user, section()))->getAvailableRoles());
});
