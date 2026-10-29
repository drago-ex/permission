<?php declare(strict_types=1);

/**
 * Test: bin/create-permission
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** @return array{int, string, string} exit code, stdout, stderr */
function runCommand(string ...$arguments): array
{
	$packageDir = dirname(__DIR__, 2);
	$process = proc_open(
		[PHP_BINARY, $packageDir . '/bin/create-permission', ...$arguments],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		$packageDir,
	);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return [proc_close($process), (string) $output, (string) $error];
}


function outputDir(): string
{
	$dir = 'tests/tmp/cli-' . bin2hex(random_bytes(4));
	return $dir;
}


test('A provider class is generated with the resource and allow rules', function () {
	$dir = outputDir();
	[$code] = runCommand(
		'PermissionProvider',
		'App\Presentation\Backend\Admin',
		'Backend:Admin',
		$dir,
		'--allow=RoleAdmin',
		'--allow=RoleUser,self::Resource,default',
	);

	Assert::same(0, $code);
	$file = dirname(__DIR__, 2) . '/' . $dir . '/PermissionProvider.php';
	Assert::match(<<<'PHP'
		%A%namespace App\Presentation\Backend\Admin;
		%A%final class PermissionProvider implements Provider
		{
		%A%Resource = 'Backend:Admin';
		%A%$acl->addResource(self::Resource);
		%A%$acl->allow(Role::RoleAdmin);
		%A%$acl->allow(Role::RoleUser, self::Resource, 'default');
		%A%
		PHP, file_get_contents($file));
	Assert::noError(fn() => (new PhpParserCheck)->check($file));
});


test('An existing file is kept unless --force is given', function () {
	$dir = outputDir();
	runCommand('PermissionProvider', 'App\Sign', 'Sign', $dir);
	$file = dirname(__DIR__, 2) . '/' . $dir . '/PermissionProvider.php';
	file_put_contents($file, '<?php // edited');

	[$code, , $error] = runCommand('PermissionProvider', 'App\Sign', 'Sign', $dir);
	Assert::same(0, $code);
	Assert::contains('File already exists', $error);
	Assert::same('<?php // edited', file_get_contents($file));

	[$code] = runCommand('PermissionProvider', 'App\Sign', 'Sign', $dir, '--force');
	Assert::same(0, $code);
	Assert::contains('class PermissionProvider', file_get_contents($file));
});


test('Invalid input ends with an error', function () {
	[$code, , $error] = runCommand('PermissionProvider');
	Assert::same(1, $code);
	Assert::contains('Invalid arguments', $error);

	[$code] = runCommand('Not-A-Class', 'App\Sign', 'Sign', outputDir());
	Assert::same(1, $code);
});


/** Checks that the generated file is valid PHP. */
final class PhpParserCheck
{
	public function check(string $file): void
	{
		exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file), $output, $code);
		Assert::same(0, $code, implode("\n", $output));
	}
}


test('An absolute output directory is used as it is', function () {
	$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'create-permission-' . bin2hex(random_bytes(4));
	[$code] = runCommand('PermissionProvider', 'App\Sign', 'Sign', $dir);

	Assert::same(0, $code);
	Assert::true(is_file($dir . DIRECTORY_SEPARATOR . 'PermissionProvider.php'));
});
