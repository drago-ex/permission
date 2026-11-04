<?php declare(strict_types=1);

namespace Drago\Permission;

use Nette\Application\UI\Component;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\InvalidStateException;
use Nette\Security\User;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use function is_string;


/**
 * How the Authorization and AccessCheck traits and the PrivilegeCatalog name and check privileges, kept in one place
 * so that a button is shown exactly when its signal is allowed and the administration offers the same names.
 */
final class Privilege
{
	/**
	 * The privilege a signal needs, null when the page is enough. It comes from the Access attribute: of the handle*()
	 * method, else of the factory createComponent<Name>() or the class of the receiving component (a control or a form)
	 * or of one of its parents below the presenter. Without the attribute it is the name of the signal, or the name
	 * of the form for its submit ("delete" form → "delete").
	 */
	public static function forSignal(IComponent $component, string $signal): ?string
	{
		$access = self::findAccess($component, $signal);
		$default = $component instanceof Form ? (string) $component->getName() : $signal;
		if ($access === null) {
			return $default;
		}

		if ($access->parameter !== null) {
			$value = $component instanceof Component ? $component->getParameter($access->parameter) : null;
			return is_string($value) && $value !== '' ? $value : $default;
		}

		return $access->privilege;
	}


	/**
	 * True when the user has at least one of the privileges on the resource (e.g. a menu item leading
	 * to several actions). A resource or role the ACL does not know is denied instead of failing with an exception.
	 */
	public static function isAllowed(User $user, string $resource, string $privilege, string ...$privileges): bool
	{
		foreach ([$privilege, ...$privileges] as $item) {
			try {
				if ($user->isAllowed($resource, $item)) {
					return true;
				}
			} catch (InvalidStateException) {
				return false;
			}
		}

		return false;
	}


	/** The Access attribute of a method (a handler or a factory), null without it. */
	public static function methodAccess(ReflectionMethod $method): ?Access
	{
		/** @var ReflectionAttribute<Access>|null $attribute */
		$attribute = $method->getAttributes(Access::class)[0] ?? null;
		return $attribute?->newInstance();
	}


	/**
	 * The Access attribute of a class or of one of its parent classes, null without it.
	 * @param ReflectionClass<covariant object> $class
	 */
	public static function classAccess(ReflectionClass $class): ?Access
	{
		for ($item = $class; $item !== false; $item = $item->getParentClass()) {
			/** @var ReflectionAttribute<Access>|null $attribute */
			$attribute = $item->getAttributes(Access::class)[0] ?? null;
			if ($attribute !== null) {
				return $attribute->newInstance();
			}
		}

		return null;
	}


	private static function findAccess(IComponent $component, string $signal): ?Access
	{
		if ($component instanceof Component) {
			$access = self::objectMethodAccess($component, $component::formatSignalMethod($signal));
			if ($access !== null) {
				return $access;
			}
		}

		for ($item = $component; $item !== null && !$item instanceof Presenter; $item = $item->getParent()) {
			$access = self::factoryAccess($item) ?? self::classAccess(new ReflectionObject($item));
			if ($access !== null) {
				return $access;
			}
		}

		return null;
	}


	/** The Access attribute of the factory createComponent<Name>() that created the component in its parent. */
	private static function factoryAccess(IComponent $component): ?Access
	{
		$parent = $component->getParent();
		$method = 'createComponent' . ucfirst((string) $component->getName());
		return $parent !== null ? self::objectMethodAccess($parent, $method) : null;
	}


	/** The Access attribute of a method of the object, null without the method or the attribute. */
	private static function objectMethodAccess(object $object, string $method): ?Access
	{
		try {
			return self::methodAccess(new ReflectionMethod($object, $method));
		} catch (ReflectionException) {
			return null;
		}
	}
}
