<?php declare(strict_types=1);

namespace Drago\Permission;

use Attribute;


/**
 * Sets the privilege a signal needs, checked by the Authorization trait on the presenter as the resource.
 * A signal without it needs the privilege named like the signal ("delete!" → "delete").
 *
 * - #[Access('edit')]: the privilege "edit", e.g. for several signals of one operation
 * - #[Access]: no privilege of its own, the page is enough (sorting, paging, a preview)
 * - #[Access(parameter: 'name')]: the privilege is the value of the signal parameter (row actions of a grid)
 *
 * On a component class it applies to every signal of the component and of its subcomponents (e.g. its form).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Access
{
	public function __construct(
		public ?string $privilege = null,
		public ?string $parameter = null,
	) {
	}
}
