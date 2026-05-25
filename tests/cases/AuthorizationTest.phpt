<?php

declare(strict_types=1);

use Drago\Permission\Authorization;
use Nette\Application\UI\Presenter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

final class TestPresenter extends Presenter
{
	public function configure(string $action, ?string $receiver, ?string $signal): void
	{
		$reflection = new ReflectionClass(Presenter::class);

		$actionProperty = $reflection->getProperty('action');
		$actionProperty->setValue($this, $action);

		$signalReceiverProperty = $reflection->getProperty('signalReceiver');
		$signalReceiverProperty->setValue($this, $receiver ?? '');

		$signalProperty = $reflection->getProperty('signal');
		$signalProperty->setValue($this, $signal);
	}
}

final class TestAuthorization
{
	use Authorization;

	public function __construct(
		private readonly array $signals = [],
		private readonly array $receivers = [],
	) {
	}


	protected function readOnlySignals(): array
	{
		return $this->signals;
	}


	protected function readOnlyReceivers(): array
	{
		return $this->receivers;
	}


	public function resolve(Presenter $presenter): string
	{
		return $this->resolveAclResource($presenter);
	}
}

$presenter = new TestPresenter;
$auth = new TestAuthorization;

$presenter->configure('default', null, null);
Assert::same('default-read', $auth->resolve($presenter));

$presenter->configure('default', 'articleGrid', 'delete');
Assert::same('articleGrid-write', $auth->resolve($presenter));

$presenter->configure('default', 'articleGrid', 'sort');
$authWithSignals = new TestAuthorization(['sort']);
Assert::same('articleGrid-read', $authWithSignals->resolve($presenter));

$presenter->configure('default', 'articleGrid-main', 'delete');
$authWithReceivers = new TestAuthorization([], ['articleGrid']);
Assert::same('articleGrid-read', $authWithReceivers->resolve($presenter));

$presenter->configure('default', null, 'ping');
Assert::same('ping', $auth->resolve($presenter));
