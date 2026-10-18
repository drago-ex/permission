<?php

/**
 * Test: bin/create-permission
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** @return array{int, string, string} exit code, stdout, stderr */
function runCommand(string ...$arguments): array
{
	return runInProject(dirname(__DIR__, 2), ...$arguments);
}


/** @return array{int, string, string} exit code, stdout, stderr */
function runInProject(string $root, string ...$arguments): array
{
	$packageDir = dirname(__DIR__, 2);
	$process = proc_open(
		[PHP_BINARY, $packageDir . '/bin/create-permission', ...$arguments],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		$root,
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
	[$code] = runCommand('PermissionProvider', 'App\Presentation\Backend\Admin', 'Backend:Admin', $dir, '--allow=RoleAdmin', '--allow=RoleUser,self::Resource,default');

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


/**
 * A project whose installed packages are only these, each with its own files.
 * @param array<string, array<string, string>> $packages name => [file => content]
 */
function createProject(array $packages): string
{
	$root = dirname(__DIR__) . '/tmp/project-' . bin2hex(random_bytes(4));
	$versions = [];
	foreach ($packages as $name => $files) {
		$path = "$root/vendor/$name";
		mkdir($path, 0o777, true);
		foreach ($files as $file => $content) {
			@mkdir(dirname("$path/$file"), 0o777, true);
			file_put_contents("$path/$file", $content);
		}
		$versions[$name] = [
			'pretty_version' => '1.2.0',
			'version' => '1.2.0.0',
			'reference' => null,
			'type' => 'library',
			'install_path' => $path,
			'aliases' => [],
			'dev_requirement' => false,
		];
	}

	$installed = [
		'root' => [
			'name' => 'acme/project',
			'pretty_version' => 'dev-main',
			'version' => 'dev-main',
			'reference' => null,
			'type' => 'project',
			'install_path' => $root,
			'aliases' => [],
			'dev' => true,
		],
		'versions' => $versions,
	];
	file_put_contents("$root/vendor/autoload.php", sprintf(
		"<?php\nrequire %s;\nComposer\\InstalledVersions::reload(%s);\n",
		var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
		var_export($installed, true),
	));

	return $root;
}


function composerJson(?string $permissionFile): string
{
	$extra = $permissionFile === null ? [] : ['extra' => ['drago-permission' => $permissionFile]];
	return (string) json_encode(['name' => 'acme/package'] + $extra);
}


function readReport(string $root): array
{
	return json_decode((string) file_get_contents("$root/.drago-project/permission.json"), true);
}


const SignDefinitions = <<<'PHP'
	<?php
	return [
		[
			'className' => 'PermissionProvider',
			'namespace' => 'App\Presentation\Sign',
			'resource' => 'Sign',
			'outputDir' => 'app/Presentation/Sign',
			'allow' => ['RoleGuest,self::Resource'],
		],
	];
	PHP;


test('Providers declared by installed packages are generated and reported', function () {
	$root = createProject([
		'acme/sign' => ['composer.json' => composerJson('permission.php'), 'permission.php' => SignDefinitions],
		'acme/article' => [
			'composer.json' => composerJson('config/permission.php'),
			'config/permission.php' => <<<'PHP'
				<?php
				return [
					[
						'className' => 'PermissionProvider',
						'namespace' => 'App\Presentation\Backend\Article',
						'resource' => 'Backend:Article',
						'outputDir' => 'app/Presentation/Backend/Article',
						'allow' => ['RoleAdmin', 'RoleEditor,self::Resource,edit'],
					],
					[
						'className' => 'ArticlePermission',
						'namespace' => 'App\Presentation\Front\Article',
						'resource' => 'Front:Article',
						'outputDir' => 'app/Presentation/Front/Article',
						'addResource' => false,
						'allowRole' => 'RoleGuest',
						'allowPrivilege' => 'view',
					],
				];
				PHP,
		],
		'acme/plain' => ['composer.json' => composerJson(null)],
	]);
	[$code, $output, $error] = runInProject($root, '--packages');

	Assert::same(0, $code, $output . $error);
	Assert::match(<<<'PHP'
		%A%namespace App\Presentation\Backend\Article;
		%A%$acl->addResource(self::Resource);
		%A%$acl->allow(Role::RoleAdmin);
		%A%$acl->allow(Role::RoleEditor, self::Resource, 'edit');
		%A%
		PHP, file_get_contents("$root/app/Presentation/Backend/Article/PermissionProvider.php"));

	$front = (string) file_get_contents("$root/app/Presentation/Front/Article/ArticlePermission.php");
	Assert::notContains('addResource', $front);
	Assert::contains("\$acl->allow(Role::RoleGuest, self::Resource, 'view');", $front);
	Assert::true(is_file("$root/app/Presentation/Sign/PermissionProvider.php"));

	$report = readReport($root);
	Assert::same('permission', $report['tool']);
	Assert::same('create-permission --packages', $report['command']);
	Assert::same('ok', $report['result']);
	Assert::same(['generated' => 3, 'skipped' => 0, 'error' => 0], $report['stats']);
	Assert::same([
		'package' => 'acme/article',
		'version' => '1.2.0',
		'action' => 'generated',
		'class' => 'App\Presentation\Backend\Article\PermissionProvider',
		'file' => 'app/Presentation/Backend/Article/PermissionProvider.php',
	], $report['entries'][0]);
	Assert::same(['acme/article', 'acme/article', 'acme/sign'], array_column($report['entries'], 'package'));
});


test('Existing providers are skipped and reported unless --force is given', function () {
	$root = createProject([
		'acme/sign' => ['composer.json' => composerJson('permission.php'), 'permission.php' => SignDefinitions],
	]);
	runInProject($root, '--packages');
	$file = "$root/app/Presentation/Sign/PermissionProvider.php";
	file_put_contents($file, '<?php // edited');

	[$code, $output, $error] = runInProject($root, '--packages');
	Assert::same(0, $code);
	Assert::same('<?php // edited', file_get_contents($file));
	Assert::contains('1 existing providers kept', $output);
	Assert::notContains('File already exists', $error);
	Assert::same(['generated' => 0, 'skipped' => 1, 'error' => 0], readReport($root)['stats']);

	[$code] = runInProject($root, '--packages', '--force');
	Assert::same(0, $code);
	Assert::contains('class PermissionProvider', file_get_contents($file));
	Assert::same('create-permission --packages --force', readReport($root)['command']);
	Assert::same('generated', readReport($root)['entries'][0]['action']);
});


test('A broken package definition is reported and the other packages are still generated', function () {
	$root = createProject([
		'acme/broken' => [
			'composer.json' => composerJson('permission.php'),
			'permission.php' => "<?php\nreturn [['className' => 'PermissionProvider', 'namespace' => 'App\\Broken', 'outputDir' => 'app/Broken', 'allowRoles' => 'RoleAdmin']];\n",
		],
		'acme/missing' => ['composer.json' => composerJson('permission.php')],
		'acme/sign' => ['composer.json' => composerJson('permission.php'), 'permission.php' => SignDefinitions],
	]);
	[$code, , $error] = runInProject($root, '--packages');

	Assert::same(1, $code);
	Assert::contains('Unknown key allowRoles', $error);
	Assert::true(is_file("$root/app/Presentation/Sign/PermissionProvider.php"));

	$report = readReport($root);
	Assert::same('error', $report['result']);
	Assert::same(['generated' => 1, 'skipped' => 0, 'error' => 2], $report['stats']);
	Assert::same('Definition #0: Unknown key allowRoles.', $report['entries'][0]['message']);
	Assert::same('Permission file not found: permission.php', $report['entries'][1]['message']);
});


test('Without declaring packages nothing is generated, but the run is reported', function () {
	$root = createProject(['acme/plain' => ['composer.json' => composerJson(null)]]);
	[$code, , $error] = runInProject($root, '--packages');

	Assert::same(0, $code);
	Assert::contains('No permission providers found', $error);
	Assert::same([], readReport($root)['entries']);
});


test('--packages cannot be combined with arguments', function () {
	[$code, , $error] = runCommand('PermissionProvider', 'App\Sign', '--packages');
	Assert::same(1, $code);
	Assert::contains('either arguments or --packages', $error);

	[$code] = runCommand('--packages', '--allow=RoleAdmin');
	Assert::same(1, $code);
});
