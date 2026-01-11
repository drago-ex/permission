<?php

declare(strict_types=1);

namespace Drago\Permission\Control;

use Drago\Application\UI\ExtraControl;


/**
 * @property-read $template RolesTemplate
 */
class RolesControl extends ExtraControl
{
	public function render(): void
	{
		$template = $this->template;
		$template->setFile(__DIR__ . '/Roles.latte');
		$template->setTranslator($this->translator);
		$template->render();
	}
}
