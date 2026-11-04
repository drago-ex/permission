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
		try {
			$class = new ReflectionClass($this->presenterFactory->getPresenterClass($presenter));
		} catch (InvalidPresenterException|ReflectionException) { // @phpstan-ignore catch.neverThrown (the class exists, PhpStorm wants it caught)
			return [];
		}

		$privileges = $this->getPages($class);
		$this->addComponent($privileges, $class, null, 0);
		return array_values(array_unique($privileges));
	}


	/**
	 * @param ReflectionClass<covariant object> $class
	 * @return list<string>
	 */
	private function getPages(ReflectionClass $class): array
	{
		$pages = [];
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			$name = $method->getName();
			foreach (['action', 'render'] as $prefix) {
				if (str_starts_with($name, $prefix) && strlen($name) > strlen($prefix)) {
					$pages[] = lcfirst(substr($name, strlen($prefix)));
				}
			}
		}

		$file = $class->getFileName();
		foreach ($file !== false ? (glob(dirname($file) . '/*.latte') ?: []) : [] as $template) {
			$name = basename($template, '.latte');
			if (!str_starts_with($name, '@')) {
				$pages[] = $name;
			}
		}

		return $pages;
	}


	/**
	 * Adds the signals of the component class: its handle*() methods and the forms and components of its factories.
	 * @param list<string> $privileges
	 * @param ReflectionClass<covariant object> $class
	 */
	private function addComponent(array &$privileges, ReflectionClass $class, ?Access $inherited, int $depth): void
	{
		$access = $depth === 0 ? null : (Privilege::classAccess($class) ?? $inherited);

		foreach ($class->getMethods() as $method) {
			$name = $method->getName();
			if (str_starts_with($name, 'handle') && strlen($name) > 6 && $method->isPublic()) {
				$this->add($privileges, Privilege::methodAccess($method) ?? $access, lcfirst(substr($name, 6)));

			} elseif (str_starts_with($name, 'createComponent') && strlen($name) > 15 && $depth < self::MaxDepth) {
				$child = $this->getReturnClass($method);
				$childAccess = Privilege::methodAccess($method) ?? $access;
				if ($child?->isSubclassOf(Form::class) || $child?->getName() === Form::class) {
					$this->add($privileges, Privilege::classAccess($child) ?? $childAccess, lcfirst(substr($name, 15)));
				} elseif ($child?->isSubclassOf(Component::class)) {
					$this->addComponent($privileges, $child, $childAccess, $depth + 1);
				}
			}
		}
	}


	/** @param list<string> $privileges */
	private function add(array &$privileges, ?Access $access, string $default): void
	{
		if ($access === null) {
			$privileges[] = $default;
		} elseif ($access->parameter === null && is_string($access->privilege) && !in_array($access->privilege, $privileges, true)) {
			$privileges[] = $access->privilege;
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
