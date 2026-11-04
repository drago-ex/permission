<?php declare(strict_types=1);

/**
 * Test: Drago\Permission\Authorization
 */

use Drago\Permission\Access;
use Drago\Permission\Authorization;
use Drago\Permission\Role;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Http\Request as HttpRequest;
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
	/** @var list<string> */
	public array $called = [];


	#[Access]
	public function handlePreview(): void
	{
		$this->called[] = 'preview';
	}


	public function handleDelete(): void
	{
		$this->called[] = 'delete';
	}


	#[Access('edit')]
	public function handleSave(): void
	{
		$this->called[] = 'save';
	}


	#[Access(parameter: 'name')]
	public function handleAction(string $name): void
	{
		$this->called[] = "action $name";
	}
}


/** Every signal of the component, and of its subcomponents, needs only the page. */
#[Access]
final class FiltersControl extends Control
{
	public function handleReset(): void
	{
	}
}


final class FormControl extends Control
{
	public function handleSubmit(): void
	{
	}
}


class GuardedPresenter extends Presenter
{
	use Authorization;

	/** @var array{string, array<string, mixed>}|null */
	public ?array $redirectedTo = null;


	#[Access]
	public function handlePing(): void
	{
	}


	public function handleLogout(): void
	{
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


/**
 * The presenter $name with the action and an optional signal ("receiver:signal") with its parameters,
 * for a user with the identity; a user may open "default" and "edit".
 * @param array<string, mixed> $params
 */
function createPresenter(
	?IIdentity $identity,
	string $name = 'Admin',
	string $action = 'default',
	?string $signal = null,
	array $params = [],
	string $method = 'GET',
): GuardedPresenter
{
	$acl = new Permission;
	$acl->addRole(Role::Guest);
	$acl->addRole(Role::User, Role::Guest);
	$acl->addResource('Admin');
	$acl->allow(Role::User, 'Admin', ['default', 'edit']);

	$presenter = new GuardedPresenter;
	$presenter->setParent(null, $name);
	$presenter->injectPrimary(
		new HttpRequest(new UrlScript('http://example.com/')),
		new Response,
		user: new User(storage: new TestUserStorage($identity), authorizator: $acl),
	);
	$presenter->injectAuthorization();
	$presenter->addComponent($articles = new ArticlesControl, 'articles');
	$presenter->addComponent($filters = new FiltersControl, 'filters');
	$filters->addComponent(new FormControl, 'form');
	$articles->loadState($params);

	[$receiver, $signalName] = $signal === null ? ['', null] : explode(':', $signal);
	$reflection = new ReflectionClass(Presenter::class);
	$reflection->getProperty('action')->setValue($presenter, $action);
	$reflection->getProperty('signalReceiver')->setValue($presenter, $receiver);
	$reflection->getProperty('signal')->setValue($presenter, $signalName);
	$reflection->getProperty('request')->setValue($presenter, new Request($name, $method));
	return $presenter;
}


function user(): SimpleIdentity
{
	return new SimpleIdentity(1, [Role::User]);
}


/** @return list<string> */
function called(GuardedPresenter $presenter): array
{
	$articles = $presenter['articles'];
	assert($articles instanceof ArticlesControl);
	return $articles->called;
}


function assertForbidden(GuardedPresenter $presenter): void
{
	Assert::exception(fn() => $presenter->processSignal(), BadRequestException::class, 'Forbidden', 403);
}


test('A page is checked by its action', function () {
	$presenter = createPresenter(user());
	$presenter->onStartup();
	Assert::null($presenter->redirectedTo);

	$presenter = createPresenter(user(), action: 'comments');
	Assert::exception(fn() => $presenter->onStartup(), BadRequestException::class, 'Forbidden', 403);
});


test('A guest is redirected to sign in with a backlink', function () {
	$presenter = createPresenter(null);
	$presenter->onStartup();
	Assert::same([':Sign:in', ['backlink' => 'stored']], $presenter->redirectedTo);
});


test('A signal or a form of a guest is not stored, so it is not replayed after signing in', function () {
	$presenter = createPresenter(null, signal: 'articles:delete');
	$presenter->onStartup();
	Assert::same([':Sign:in', ['backlink' => null]], $presenter->redirectedTo);

	$presenter = createPresenter(null, method: 'POST');
	$presenter->onStartup();
	Assert::same([':Sign:in', ['backlink' => null]], $presenter->redirectedTo);
});


test('A presenter without a registered ACL resource is denied, not a server error', function () {
	$presenter = createPresenter(user(), name: 'Unregistered');
	Assert::exception(fn() => $presenter->onStartup(), BadRequestException::class, 'Forbidden', 403);

	$presenter = createPresenter(null, name: 'Unregistered');
	$presenter->onStartup();
	Assert::same(':Sign:in', $presenter->redirectedTo[0]);
});


test('A signal needs the privilege named like it, before its handler runs', function () {
	$presenter = createPresenter(user(), signal: 'articles:delete');
	assertForbidden($presenter);
	Assert::same([], called($presenter));
});


test('Access sets the privilege of a signal', function () {
	$presenter = createPresenter(user(), signal: 'articles:save');
	$presenter->processSignal();
	Assert::same(['save'], called($presenter));
});


test('Access without a privilege lets the page decide', function () {
	$presenter = createPresenter(user(), signal: 'articles:preview');
	$presenter->processSignal();
	Assert::same(['preview'], called($presenter));
});


test('Access with a parameter takes the privilege from the signal parameter', function () {
	$presenter = createPresenter(user(), signal: 'articles:action', params: ['name' => 'edit']);
	$presenter->processSignal();
	Assert::same(['action edit'], called($presenter));

	assertForbidden(createPresenter(user(), signal: 'articles:action', params: ['name' => 'delete']));
});


test('Access on a component class covers its signals and those of its subcomponents', function () {
	Assert::noError(fn() => createPresenter(user(), signal: 'filters:reset')->processSignal());
	Assert::noError(fn() => createPresenter(user(), signal: 'filters-form:submit')->processSignal());
});


test('Signals of the presenter itself follow the same rules', function () {
	Assert::noError(fn() => createPresenter(user(), signal: ':ping')->processSignal());
	assertForbidden(createPresenter(user(), signal: ':logout'));
});


test('A signal of a missing component needs the privilege named like it', function () {
	assertForbidden(createPresenter(user(), signal: 'missing:delete'));
});
