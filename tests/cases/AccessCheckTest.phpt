<?php declare(strict_types=1);

/**
 * Test: Drago\Permission\AccessCheck
 */

use Drago\Permission\AccessCheck;
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
	use AccessCheck;
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


/** Whether the control "articles" in the presenter $presenterName shows its delete button to a user with the role. */
function isDeleteAllowed(string $role, string $presenterName): bool
{
	$acl = new Permission;
	$acl->addRole('editor');
	$acl->addRole('reader');
	$acl->addResource('Backend:Blog');
	$acl->allow('editor', 'Backend:Blog', ['default', 'delete']);
	$acl->allow('reader', 'Backend:Blog', 'default');

	$user = new User(storage: new SignedInStorage(new SimpleIdentity(1, [$role])), authorizator: $acl);
	$presenter = new class extends Presenter {
	};
	$presenter->setParent(null, $presenterName);
	$presenter->injectPrimary(new Request(new UrlScript('http://example.com/')), new Response, user: $user);
	$presenter->addComponent($control = new ArticlesControl, 'articles');

	return $control->isAllowed('delete');
}


test('The privilege on the presenter of the component shows the button', function () {
	Assert::true(isDeleteAllowed('editor', 'Backend:Blog'));
	Assert::false(isDeleteAllowed('reader', 'Backend:Blog'));
});


test('A presenter without an ACL resource hides it, as the Authorization trait denies it', function () {
	Assert::false(isDeleteAllowed('editor', 'Backend:Unknown'));
});
