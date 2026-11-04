<?php declare(strict_types=1);

/**
 * Test: Drago\Permission\Privilege
 */

use Drago\Permission\Privilege;
use Fixture\Blog\BlogPresenter;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/Blog/BlogPresenter.php';


final class IdentityStorage implements UserStorage
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


$acl = new Permission;
$acl->addRole('editor');
$acl->addResource('Backend:Permission');
$acl->allow('editor', 'Backend:Permission', 'users');
$user = new User(storage: new IdentityStorage(new SimpleIdentity(1, ['editor'])), authorizator: $acl);


test('Any of the privileges allows the resource', function () use ($user) {
	Assert::true(Privilege::isAllowed($user, 'Backend:Permission', 'users'));
	Assert::true(Privilege::isAllowed($user, 'Backend:Permission', 'default', 'users'));
	Assert::false(Privilege::isAllowed($user, 'Backend:Permission', 'default', 'delete'));
});


test('A resource the ACL does not know is denied', function () use ($user) {
	Assert::false(Privilege::isAllowed($user, 'Backend:Missing', 'default'));
});


test('A form is named by itself, or by the Access attribute of its factory', function () {
	$presenter = new BlogPresenter;
	Assert::same('archive', Privilege::forSignal($presenter['articles-archive'], 'submit'));
	Assert::same('approve', Privilege::forSignal($presenter['articles-comments'], 'submit'));
});


test('The Access attribute of a factory covers every signal of the component', function () {
	Assert::same('edit', Privilege::forSignal((new BlogPresenter)['import'], 'upload'));
});
