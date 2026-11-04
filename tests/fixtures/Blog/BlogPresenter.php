<?php declare(strict_types=1);

namespace Fixture\Blog;

use Drago\Permission\Access;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;


/** A presenter of the PrivilegeCatalog test, with the templates default.latte and categories.latte next to it. */
final class BlogPresenter extends Presenter
{
	public function actionEdit(int $id): void
	{
	}


	#[Access]
	public function handlePing(): void
	{
	}


	public function renderDefault(): void
	{
	}


	protected function createComponentArticles(): ArticlesControl
	{
		return new ArticlesControl;
	}


	#[Access('edit')]
	protected function createComponentImport(): ImportControl
	{
		return new ImportControl;
	}
}


final class ArticlesControl extends Control
{
	public function handleDelete(int $id): void
	{
	}


	#[Access('edit')]
	public function handleSave(): void
	{
	}


	#[Access]
	public function handlePreview(): void
	{
	}


	#[Access(parameter: 'name')]
	public function handleAction(string $name): void
	{
	}


	#[Access('approve')]
	protected function createComponentComments(): Form
	{
		return new Form;
	}


	protected function createComponentArchive(): Form
	{
		return new Form;
	}
}


/** Every signal of the component needs what its factory sets. */
final class ImportControl extends Control
{
	public function handleUpload(): void
	{
	}
}
