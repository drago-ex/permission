<?php

declare(strict_types=1);

namespace Drago\Permission\Tracy;

use Nette\Application\Application;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Session;
use Nette\Security\User;
use Tracy\Helpers;
use Tracy\IBarPanel;


/**
 * Tracy bar panel for switching the roles of the signed-in user while developing.
 * Register it in the `tracy: bar:` section; Nette adds bar panels only in debug mode.
 */
final class RoleSwitchPanel implements IBarPanel
{
	private RoleSwitch $roleSwitch;


	public function __construct(
		Application $application,
		User $user,
		Session $session,
		private readonly IRequest $request,
		private readonly IResponse $response,
	) {
		$this->roleSwitch = new RoleSwitch($user, $session->getSection(RoleSwitch::class));
		$application->onStartup[] = $this->startup(...);
	}


	public function getTab(): string
	{
		$roles = $this->roleSwitch->isSignedIn()
			? implode(', ', $this->roleSwitch->getCurrentRoles()) ?: 'no role'
			: 'guest';
		$style = $this->roleSwitch->isSwitched() ? ' style="color: #c00; font-weight: bold"' : '';

		return '<span title="Role switch"' . $style . '>'
			. '<svg viewBox="0 0 16 16"><path d="M8 1 2 3.5v4c0 3.6 2.6 6.4 6 7.5 3.4-1.1 6-3.9 6-7.5v-4z" fill="#2a8f6b"/><path d="m5 8 2 2 4-4" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '<span class="tracy-label">' . Helpers::escapeHtml($roles) . '</span></span>';
	}


	public function getPanel(): string
	{
		$roleSwitch = $this->roleSwitch;
		$action = (string) $this->request->getUrl();

		ob_start();
		require __DIR__ . '/RoleSwitchPanel.phtml';
		return (string) ob_get_clean();
	}


	private function startup(): void
	{
		if ($this->roleSwitch->handle($this->request)) {
			$this->response->redirect((string) $this->request->getUrl(), IResponse::S303_PostGet);
			exit;
		}

		$this->roleSwitch->apply();
	}
}
