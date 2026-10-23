<?php

/**
 * Test: Drago\Permission\WriteAccess
 */

declare(strict_types=1);

use Drago\Permission\WriteAccess;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


final class ArticlesControl extends Control
{
	use WriteAccess;
}


final class SignedInStorage implements UserStorage
{
	public function __construct(
		private IIdentity $identity,
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
		return [true, $this->identity, null];
	}


	public function setExpiration(?string $expire, bool $clearIdentity): void
	{
	}
}


/** The control "articles" in the presenter $presenterName, for a user with the given role. */
function isWriteAllowed(string $role, string $presenterName): bool
{
	$acl = new Permission;
	$acl->addRole('editor');
	$acl->addRole('reader');
	$acl->addResource('Backend:Blog');
	$acl->allow('editor', 'Backend:Blog', 'articles-write');
	$acl->allow('reader', 'Backend:Blog', 'articles-read');

	$user = new User(storage: new SignedInStorage(new SimpleIdentity(1, [$role])), authorizator: $acl);
	$presenter = new class extends Presenter {
	};
	$presenter->setParent(null, $presenterName);
	$presenter->injectPrimary(new Request(new UrlScript('http://example.com/')), new Response, user: $user);
	$presenter->addComponent($control = new ArticlesControl, 'articles');

	return $control->isWriteAllowed();
}


test('The "{component}-write" privilege of the presenter shows the write buttons', function () {
	Assert::true(isWriteAllowed('editor', 'Backend:Blog'));
	Assert::false(isWriteAllowed('reader', 'Backend:Blog'));
});


test('A presenter without an ACL resource hides them, as the Authorization trait denies it', function () {
	Assert::false(isWriteAllowed('editor', 'Backend:Unknown'));
});
