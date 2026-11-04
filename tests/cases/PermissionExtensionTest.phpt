<?php declare(strict_types=1);

/**
 * Test: Drago\Permission\Latte\PermissionExtension
 */

use Drago\Permission\Latte\PermissionExtension;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


final class EditorStorage implements UserStorage
{
	public function saveAuthentication(IIdentity $identity): void
	{
	}


	public function clearAuthentication(bool $clearIdentity): void
	{
	}


	public function getState(): array
	{
		return [true, new SimpleIdentity(1, ['editor']), null];
	}


	public function setExpiration(?string $expire, bool $clearIdentity): void
	{
	}
}


$acl = new Permission;
$acl->addRole('editor');
$acl->addResource('Backend:Permission');
$acl->allow('editor', 'Backend:Permission', 'users');

$latte = new Engine;
$latte->setLoader(new StringLoader);
$latte->addExtension(new PermissionExtension(new User(storage: new EditorStorage, authorizator: $acl)));


test('isAllowed() shows what the user may open', function () use ($latte) {
	Assert::same('<span>users</span>', $latte->renderToString(
		'<span n:if="isAllowed(\'Backend:Permission\', \'users\')">users</span>'
		. '<span n:if="isAllowed(\'Backend:Permission\', \'roles\')">roles</span>',
	));
});


test('isAllowed() is true for any of the privileges and false for an unknown resource', function () use ($latte) {
	Assert::same('yes', $latte->renderToString('{if isAllowed("Backend:Permission", "roles", "users")}yes{/if}'));
	Assert::same('', $latte->renderToString('{if isAllowed("Backend:Missing", "default")}yes{/if}'));
});
