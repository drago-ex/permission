<?php declare(strict_types=1);

/**
 * Test: Drago\Permission\PrivilegeCatalog
 */

use Drago\Permission\PrivilegeCatalog;
use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/Blog/BlogPresenter.php';


$catalog = new PrivilegeCatalog(new class implements IPresenterFactory {
	public function getPresenterClass(string &$name): string
	{
		return $name === 'Backend:Blog'
			? Fixture\Blog\BlogPresenter::class
			: throw new InvalidPresenterException("Cannot load presenter '$name'.");
	}


	public function createPresenter(string $name): IPresenter
	{
		throw new LogicException('Not used.');
	}
});


test('Pages come from the actions, the render methods and the templates', function () use ($catalog) {
	$privileges = $catalog->getPrivileges('Backend:Blog');
	Assert::same(['edit', 'default', 'categories'], array_slice($privileges, 0, 3));
});


test('Signals are named by Access or by themselves, page-only and parameter ones are left out', function () use ($catalog) {
	$privileges = $catalog->getPrivileges('Backend:Blog');
	sort($privileges);
	Assert::same(['approve', 'archive', 'categories', 'default', 'delete', 'edit'], $privileges);
});


test('Sources tell which places of the code need each privilege', function () use ($catalog) {
	$sources = $catalog->getSources('Backend:Blog');
	Assert::same([PrivilegeCatalog::Page => ['actionEdit'], PrivilegeCatalog::Signal => ['ArticlesControl::handleSave', 'ImportControl::handleUpload']], $sources['edit']);
	Assert::same([PrivilegeCatalog::Page => ['renderDefault', 'default.latte']], $sources['default']);
	Assert::same([PrivilegeCatalog::Page => ['categories.latte']], $sources['categories']);
	Assert::same([PrivilegeCatalog::Signal => ['ArticlesControl::handleDelete']], $sources['delete']);
	Assert::same([PrivilegeCatalog::Form => ['comments']], $sources['approve']);
	Assert::same([PrivilegeCatalog::Form => ['archive']], $sources['archive']);
	Assert::same($catalog->getPrivileges('Backend:Blog'), array_keys($sources));
});


test('A resource that is no presenter has no privileges', function () use ($catalog) {
	Assert::same([], $catalog->getPrivileges('Front:Missing'));
	Assert::same([], $catalog->getSources('Front:Missing'));
});
