<?php

/**
 * Test: Drago\Permission\Authorization
 */

declare(strict_types=1);

use Drago\Permission\Authorization;
use Drago\Permission\Role;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class TestPresenter extends Presenter
{
	use Authorization;

	public ?array $redirectedTo = null;

	/** @var list<string> */
	private array $signals = [];

	/** @var list<string> */
	private array $receivers = [];


	public function configure(
		string $action,
		?string $receiver,
		?string $signal,
		array $signals = [],
		array $receivers = [],
	): void
	{
		$reflection = new ReflectionClass(Presenter::class);
		$reflection->getProperty('action')->setValue($this, $action);
		$reflection->getProperty('signalReceiver')->setValue($this, $receiver ?? '');
		$reflection->getProperty('signal')->setValue($this, $signal);
		$this->signals = $signals;
		$this->receivers = $receivers;
	}


	public function resolve(): string
	{
		return $this->resolveAclResource($this);
	}


	public function flashMessage($message, string $type = 'info'): stdClass
	{
		return (object) ['message' => $message, 'type' => $type];
	}


	public function storeRequest(string $expiration = '+ 10 minutes'): string
	{
		return 'stored';
	}


	public function redirect(string $destination, $args = []): void
	{
		$this->redirectedTo = [$destination, $args];
	}


	protected function readOnlySignals(): array
	{
		return $this->signals;
	}


	protected function readOnlyReceivers(): array
	{
		return $this->receivers;
	}
}


final class TestUserStorage implements UserStorage
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


function createPresenter(?IIdentity $identity): TestPresenter
{
	$acl = new Permission;
	$acl->addRole(Role::RoleGuest);
	$acl->addRole(Role::RoleUser, Role::RoleGuest);
	$acl->addResource('Admin');
	$acl->allow(Role::RoleUser, 'Admin', 'default-read');

	$presenter = new TestPresenter;
	$user = new User(storage: new TestUserStorage($identity), authorizator: $acl);
	$presenter->injectAuthorization($presenter, $user);
	return $presenter;
}


function startup(TestPresenter $presenter, string $name, string $action): void
{
	$presenter->setParent(null, $name);
	$presenter->configure($action, null, null);
	$presenter->onStartup();
}


test('Privileges are resolved from the action and signals', function () {
	$presenter = new TestPresenter;

	$presenter->configure('default', null, null);
	Assert::same('default-read', $presenter->resolve());

	$presenter->configure('default', 'articleGrid', 'delete');
	Assert::same('articleGrid-write', $presenter->resolve());

	$presenter->configure('default', 'articleGrid', 'sort', signals: ['sort']);
	Assert::same('articleGrid-read', $presenter->resolve());

	$presenter->configure('default', 'articleGrid-main', 'delete', receivers: ['articleGrid']);
	Assert::same('articleGrid-read', $presenter->resolve());

	$presenter->configure('default', null, 'ping');
	Assert::same('ping', $presenter->resolve());
});


test('An allowed user passes', function () {
	$presenter = createPresenter(new SimpleIdentity(1, [Role::RoleUser]));
	startup($presenter, 'Admin', 'default');
	Assert::null($presenter->redirectedTo);
});


test('A guest is redirected to sign in with a backlink', function () {
	$presenter = createPresenter(null);
	startup($presenter, 'Admin', 'default');
	Assert::same([':Sign:in', ['backlink' => 'stored']], $presenter->redirectedTo);
});


test('A signed-in user without the privilege gets 403', function () {
	$presenter = createPresenter(new SimpleIdentity(1, [Role::RoleUser]));
	Assert::exception(fn() => startup($presenter, 'Admin', 'edit'), BadRequestException::class, 'Forbidden', 403);
});


test('A presenter without a registered ACL resource is denied, not a server error', function () {
	$presenter = createPresenter(new SimpleIdentity(1, [Role::RoleUser]));
	Assert::exception(fn() => startup($presenter, 'Unregistered', 'default'), BadRequestException::class, 'Forbidden', 403);

	$presenter = createPresenter(null);
	startup($presenter, 'Unregistered', 'default');
	Assert::same(':Sign:in', $presenter->redirectedTo[0]);
});


test('A presenter can decide about read-only signals itself', function () {
	$presenter = new class extends TestPresenter {
		protected function isReadOnlySignal(string $receiver, string $name): bool
		{
			return str_ends_with($receiver, '-paginator');
		}
	};

	$presenter->configure('default', 'articles-dataGrid-paginator', 'page');
	Assert::same('articles-read', $presenter->resolve());

	$presenter->configure('default', 'articles-dataGrid', 'action');
	Assert::same('articles-write', $presenter->resolve());
});
