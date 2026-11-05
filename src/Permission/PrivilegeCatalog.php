<?php declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\InvalidPresenterException;
use Nette\Application\IPresenterFactory;
use Nette\Application\UI\Component;
use Nette\Application\UI\Form;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use function is_string, strlen;


/**
 * Reads the privileges of a presenter from its code, with the same rules the Authorization trait checks:
 * the pages (action*(), render*() and the templates next to the presenter) and the signals of the presenter and of
 * its components (handle*() methods and forms, named by the Access attribute or by their own name). Signals that
 * need only the page or take the privilege from a parameter are left out. For an administration of permissions.
 */
final readonly class PrivilegeCatalog
{
	/** Kinds of the places that need a privilege. */
	public const string
		Page = 'page',
		Signal = 'signal',
		Form = 'form';

	/** How deep components inside components are read (a grid in a control is the second level). */
	private const int MaxDepth = 3;


	public function __construct(
		private IPresenterFactory $presenterFactory,
	) {
	}


	/**
	 * The privileges of the presenter (the resource), pages first; empty for a resource that is no presenter.
	 * @return list<string>
	 */
	public function getPrivileges(string $presenter): array
	{
		return array_map(strval(...), array_keys($this->getSources($presenter)));
	}


	/**
	 * The privileges of the presenter with the places of its code that need them, pages first: by privilege the
	 * kinds (Page, Signal, Form) with the names of the places (renderDefault, default.latte,
	 * ArticleControl::handleDelete, the form name). Empty for a resource that is no presenter.
	 * @return array<string, array<string, list<string>>>
	 */
	public function getSources(string $presenter): array
	{
		try {
			$class = new ReflectionClass($this->presenterFactory->getPresenterClass($presenter));
		} catch (InvalidPresenterException|ReflectionException) { // @phpstan-ignore catch.neverThrown (the class exists, PhpStorm wants it caught)
			return [];
		}

		$sources = [];
		$this->addPages($sources, $class);
		$this->addComponent($sources, $class, null, 0);
		return $sources;
	}


	/**
	 * @param array<string, array<string, list<string>>> $sources
	 * @param ReflectionClass<covariant object> $class
	 */
	private function addPages(array &$sources, ReflectionClass $class): void
	{
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			$name = $method->getName();
			foreach (['action', 'render'] as $prefix) {
				if (str_starts_with($name, $prefix) && strlen($name) > strlen($prefix)) {
					$this->addSource($sources, lcfirst(substr($name, strlen($prefix))), self::Page, $name);
				}
			}
		}

		$file = $class->getFileName();
		foreach ($file !== false ? (glob(dirname($file) . '/*.latte') ?: []) : [] as $template) {
			$name = basename($template, '.latte');
			if (!str_starts_with($name, '@')) {
				$this->addSource($sources, $name, self::Page, basename($template));
			}
		}
	}


	/**
	 * Adds the signals of the component class: its handle*() methods and the forms and components of its factories.
	 * @param array<string, array<string, list<string>>> $sources
	 * @param ReflectionClass<covariant object> $class
	 */
	private function addComponent(array &$sources, ReflectionClass $class, ?Access $inherited, int $depth): void
	{
		$access = $depth === 0 ? null : (Privilege::classAccess($class) ?? $inherited);

		foreach ($class->getMethods() as $method) {
			$name = $method->getName();
			if (str_starts_with($name, 'handle') && strlen($name) > 6 && $method->isPublic()) {
				$privilege = $this->getPrivilege(Privilege::methodAccess($method) ?? $access, lcfirst(substr($name, 6)));
				if ($privilege !== null) {
					$this->addSource($sources, $privilege, self::Signal, $class->getShortName() . '::' . $name);
				}

			} elseif (str_starts_with($name, 'createComponent') && strlen($name) > 15 && $depth < self::MaxDepth) {
				$child = $this->getReturnClass($method);
				$childAccess = Privilege::methodAccess($method) ?? $access;
				if ($child?->isSubclassOf(Form::class) || $child?->getName() === Form::class) {
					$formName = lcfirst(substr($name, 15));
					$privilege = $this->getPrivilege(Privilege::classAccess($child) ?? $childAccess, $formName);
					if ($privilege !== null) {
						$this->addSource($sources, $privilege, self::Form, $formName);
					}
				} elseif ($child?->isSubclassOf(Component::class)) {
					$this->addComponent($sources, $child, $childAccess, $depth + 1);
				}
			}
		}
	}


	/** The privilege a signal needs: by the Access attribute or its own name; null when the page is enough. */
	private function getPrivilege(?Access $access, string $default): ?string
	{
		if ($access === null) {
			return $default;
		}

		return $access->parameter === null && is_string($access->privilege) ? $access->privilege : null;
	}


	/** @param array<string, array<string, list<string>>> $sources */
	private function addSource(array &$sources, string $privilege, string $kind, string $name): void
	{
		if (!in_array($name, $sources[$privilege][$kind] ?? [], true)) {
			$sources[$privilege][$kind][] = $name;
		}
	}


	/** @return ReflectionClass<object>|null */
	private function getReturnClass(ReflectionMethod $method): ?ReflectionClass
	{
		$type = $method->getReturnType();
		if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !class_exists($type->getName())) {
			return null;
		}

		try {
			return new ReflectionClass($type->getName());
		} catch (ReflectionException) {
			return null;
		}
	}
}
