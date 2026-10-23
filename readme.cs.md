# Drago Permission

[English](readme.md) | Čeština

Malá správa ACL a rolí.
Balíček poskytuje centrální továrnu ACL, registraci oprávnění po modulech
a automatickou kontrolu autorizace v presenterech.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/drago-ex/permission/blob/main/license)
[![PHP version](https://badge.fury.io/ph/drago-ex%2Fpermission.svg)](https://badge.fury.io/ph/drago-ex%2Fpermission)
[![Tests](https://github.com/drago-ex/permission/actions/workflows/tests.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/tests.yml)
[![Coding Style](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml/badge.svg)](https://github.com/drago-ex/permission/actions/workflows/coding-style.yml)

## Požadavky

- PHP >= 8.3, < 9
- Nette Framework
- Composer

## Instalace

```bash
composer require drago-ex/permission
```

## Funkce

- Centrální vytvoření ACL
- Providery oprávnění po modulech
- Výchozí role: guest, user, admin
- Automatická autorizace presenterů
- Privilegia podle akcí a signálů

## Související balíček: dynamická správa rolí

Pro dynamickou správu rolí a přístupů použijte:

- `drago-ex/project-permission`: https://github.com/drago-ex/project-permission

Tento balíček je postavený na `drago-ex/permission` a poskytuje:

- vytváření rolí
- přiřazování rolí uživatelům
- povolení nebo zakázání přístupu pro jednotlivé role

## Role

Výchozí role:

- guest
- user (dědí z guest)
- admin (dědí z user)

Role se registrují automaticky.

## Továrna oprávnění

PermissionFactory vytvoří instanci Nette\Security\Permission,
zaregistruje výchozí role a spustí všechny registrované providery oprávnění.

Providery se sbírají přes DI tagy.

## Providery oprávnění

Každý modul registruje svá oprávnění vlastní implementací Provider.

Providery:
- registrují zdroje ACL
- definují pravidla allow / deny
- žijí uvnitř modulu, ke kterému patří

Obvyklé pojmenování zdrojů:
- Backend:Sign
- Frontend:Article

Příklad provideru pro modul Sign:

```php
use Drago\Permission\Provider;
use Drago\Permission\Role;
use Nette\Security\Permission;

final class PermissionProvider implements Provider
{
	private const string Resource = 'Backend:Sign';


	public function register(Permission $acl): void
	{
		$acl->addResource(self::Resource);
		$acl->allow(Role::RoleGuest, self::Resource);
	}
}
```

Tím se zaregistruje zdroj `Backend:Sign` a povolí se přístup návštěvníkům (nepřihlášeným uživatelům),
což je minimum pro dostupnost přihlašovací stránky.

## Generátor oprávnění (příkazová řádka)

Balíček obsahuje generátor providerů modulů:

```bash
vendor/bin/create-permission
```

Obecné použití:

```bash
vendor/bin/create-permission <ClassName> <Namespace> [Resource] [OutputDir] [options]
```

Když používáte společný název třídy `PermissionProvider`, předejte zdroj a výstupní adresář výslovně.
Název třídy je záměrně v každém modulu stejný a o tom, kam provider patří, rozhoduje jmenný prostor.

Příklad pro modul Sign:

```bash
vendor/bin/create-permission PermissionProvider App\Presentation\Sign Sign app/Presentation/Sign
```

Příklad pro modul Admin:

```bash
vendor/bin/create-permission PermissionProvider App\Presentation\Backend\Admin Backend:Admin app/Presentation/Backend/Admin --allow-role=RoleAdmin --allow-with-resource=0
```

### Volby

- `--allow-role=<RoleConstant>` výchozí `RoleGuest`
- `--allow-privilege=<string>` volitelný argument privilegia pro `allow()`
- `--add-resource=<0|1>` výchozí `1` (vygeneruje `$acl->addResource(...)`)
- `--allow-with-resource=<0|1>` výchozí `1` (vygeneruje `allow(..., self::Resource)`)
- `--allow=<rule>` opakovatelná vlastní pravidla allow
- `--force` přepíše existující soubor

Formáty `--allow`:

- `--allow=RoleAdmin`
- `--allow=RoleUser,self::Resource`
- `--allow=RoleUser,self::Resource,default`

Příklad s více pravidly:

```bash
vendor/bin/create-permission PermissionProvider App\Presentation\Backend\Admin Backend:Admin app/Presentation/Backend/Admin --allow=RoleAdmin --allow=RoleUser,self::Resource,default
```

Příklad vygenerovaného `register()`:

```php
public function register(Permission $acl): void
{
	$acl->addResource(self::Resource);
	$acl->allow(Role::RoleAdmin);
	$acl->allow(Role::RoleUser, self::Resource, 'default');
}
```

### Providery deklarované balíčky

Balíček deklaruje své providery v `composer.json`:

```json
"extra": {
	"drago-permission": "permission.php"
}
```

Soubor vrací seznam definic se stejnými hodnotami jako volby výše
(`className`, `namespace`, `resource`, `outputDir`, `allow`, `allowRole`, `allowPrivilege`, `addResource`, `allowWithResource`):

```php
return [
	[
		'className' => 'PermissionProvider',
		'namespace' => 'App\Presentation\Sign',
		'resource' => 'Sign',
		'outputDir' => 'app/Presentation/Sign',
		'allow' => ['RoleGuest,self::Resource'],
	],
];
```

Vygenerování providerů všech nainstalovaných balíčků:

```bash
vendor/bin/create-permission --packages
```

Existující třídy se ponechají, `--force` je přepíše. Výsledek se zapíše do `.drago-project/permission.json`.

## Konfigurace DI

Balíček obsahuje hotovou konfiguraci ve `vendor/drago-ex/permission/src/Permission/conf.neon`.
Nenačítá se automaticky; připojte ji do své konfigurace, nebo zkopírujte její obsah:

```neon
includes:
	- ../vendor/drago-ex/permission/src/Permission/conf.neon
```

Sekce `search` potřebuje `nette/robot-loader`, který tento balíček vyžaduje. Dodaná konfigurace obsahuje:

- registraci služby `permissionFactory`
- automatickou registraci tříd `PermissionProvider` v `%appDir%/Presentation` přes `search`

Továrna oprávnění:
```neon
services:
	permissionFactory:
		class: Drago\Permission\PermissionFactory
		arguments: [tagged(PermissionTag)]

	- @permissionFactory::create
```

Provider modulu:
```neon
services:
	signPermission:
		class: App\Presentation\Sign\PermissionProvider
		tags: [PermissionTag]
```

Ve větších aplikacích s mnoha providery můžete místo vypisování každého z nich použít sekci `search`, která zaregistruje všechny odpovídající třídy automaticky:
```neon
search:
	permissions:
		in: %appDir%/Presentation
		classes: [PermissionProvider]
		tags: [PermissionTag]
```

## Autorizace presenterů

Autorizaci obstarává trait `Authorization`.

- spouští se automaticky při startu presenteru
- kontroluje ACL podle názvu presenteru a odvozeného privilegia

Pro zapnutí autorizace v presenteru připojte trait:

```php
use Drago\Permission\Authorization;

class BasePresenter extends Nette\Application\UI\Presenter
{
	use Authorization;
}
```

Všechny presentery, které dědí z `BasePresenter`, pak mají automatickou kontrolu autorizace.

Neoprávněný přístup:
- nepřihlášený: přesměrování na `:Sign:in` s backlinkem (cíl změníte přepsáním `signInDestination()`)
- přihlášený bez oprávnění: HTTP 403
- presenter bez registrovaného zdroje ACL se zamítne stejně, takže zapomenutý provider nikdy stránku neotevře

### Odvození privilegia

Trait podle aktuálního požadavku automaticky určí, které privilegium ACL zkontrolovat:

| Situace                          | Odvozené privilegium |
|----------------------------------|----------------------|
| Načtení stránky (bez signálu)    | `{action}-read`      |
| Signál od komponenty jen pro čtení | `{component}-read` |
| Signál uvedený jako jen pro čtení | `{component}-read`  |
| Jakýkoli jiný signál             | `{component}-write`  |
| Přímý signál presenteru (bez komponenty) | `{signal}`   |

Pro signály komponent vrací Nette z `Presenter::getSignal()` dvojici:

```php
[$receiver, $signal] = $presenter->getSignal();
```

Příjemce je cesta komponenty spojená pomlčkami. Pokud má například presenter komponentu `roles`
a ta obsahuje komponentu `dataGrid` s podkomponentou `paginator`,
může Nette vytvořit signál jako:

```text
roles-dataGrid-paginator:page
```

Ten se rozloží takto:

```php
$receiver = 'roles-dataGrid-paginator';
$signal = 'page';
```

Trait autorizace použije jako skupinu privilegia první část příjemce:

```php
$group = explode('-', $receiver)[0]; // "roles"
```

Signál výše se tedy vyhodnotí jako:

```text
roles-read
```

když je nastavený jako jen pro čtení, jinak jako:

```text
roles-write
```

### Signály jen pro čtení

Přepište `readOnlySignals()` a uveďte názvy signálů, které se mají brát jako čtení.
Kontroluje se jen název signálu, ne cesta komponenty.

```php
protected function readOnlySignals(): array
{
	return ['sort', 'page', 'resetFilters', 'setPageSize'];
}
```

Příklady:

| Signál Nette                      | Příjemce                 | Signál          | Odvozené privilegium |
|-----------------------------------|--------------------------|-----------------|----------------------|
| `roles-dataGrid:sort`             | `roles-dataGrid`         | `sort`          | `roles-read`         |
| `roles-dataGrid-paginator:page`   | `roles-dataGrid-paginator` | `page`        | `roles-read`         |
| `roles-dataGrid:action`           | `roles-dataGrid`         | `action`        | `roles-write`        |

### Komponenty jen pro čtení

Přepište `readOnlyReceivers()` a uveďte části cest komponent, jejichž signály se mají vždy
vyhodnotit jako čtení. Kontroluje se cesta příjemce, ne název signálu.

```php
protected function readOnlyReceivers(): array
{
	return ['Grid-filters', 'Grid-paginator', 'Grid-pageSize'];
}
```

Každý signál, jehož příjemce obsahuje některý z těchto řetězců, se vyhodnotí jako `{component}-read` bez ohledu na název signálu.

Hodí se to pro vnořené komponenty s obecným názvem signálu, například odeslání formuláře filtru:

```text
roles-dataGrid-filters-form:submit
```

Název signálu je `submit`, ale příjemce obsahuje `Grid-filters`, takže se vyhodnotí jako:

```text
roles-read
```

Pozor na široké masky příjemce jako `Grid`. Udělají jen pro čtení každý signál daného příjemce,
včetně akcí řádků jako:

```text
roles-dataGrid:action
```

Široké masky příjemce používejte jen tehdy, když je celá komponenta jen pro čtení.

### Rozhodování v kódu

Pro víc než seznamy názvů přepište `isReadOnlySignal()`. Dostane cestu příjemce a název signálu
a nahrazuje oba seznamy. Drago DataGrid zná své signály jen pro čtení (řazení, filtry, stránkování,
velikost stránky), takže presenter s gridy se ho může jednoduše zeptat:

```php
use Drago\Datagrid\DataGrid;

protected function isReadOnlySignal(string $receiver, string $name): bool
{
	return DataGrid::isReadOnlySignal($receiver, $name);
}
```

Nezáleží to na názvu komponenty gridu; akce řádků (`roles-dataGrid:action`)
zůstávají `roles-write`.

### Úplný příklad

```php
use Drago\Permission\Authorization;

class ArticlePresenter extends BasePresenter
{
	protected function readOnlySignals(): array
	{
		// tyto signály jen čtou data, kontrolují se jako "{component}-read"
		return ['sort', 'page', 'resetFilters', 'setPageSize'];
	}

	protected function readOnlyReceivers(): array
	{
		// podkomponenty DataGridu jen pro čtení s obecnými názvy signálů
		return ['Grid-filters', 'Grid-paginator', 'Grid-pageSize'];
	}
}
```

S touto konfigurací:

| Signál Nette                         | Odvozené privilegium |
|--------------------------------------|----------------------|
| `roles-dataGrid:sort`                | `roles-read`         |
| `roles-dataGrid-paginator:page`      | `roles-read`         |
| `roles-dataGrid-filters:resetFilters`| `roles-read`         |
| `roles-dataGrid-filters-form:submit` | `roles-read`         |
| `roles-dataGrid-pageSize:setPageSize`| `roles-read`         |
| `roles-dataGrid:action`              | `roles-write`        |

Pokud si nejste jistí, co Nette pro konkrétní požadavek vytvoří, dočasně si to vypište:

```php
bdump($this->getSignal());
```

nebo hodnotu zalogujte uvnitř `resolveAclResource()`.

## Tlačítka pro zápis

Trait `WriteAccess` umožní komponentě zobrazit tlačítka pro přidání, úpravu a mazání jen těm, kdo je smí použít.
`isWriteAllowed()` se ptá na stejné privilegium `{component}-write`, jaké trait `Authorization` kontroluje pro
signály komponenty, takže funguje se statickými providery i s rolemi z databáze:

```php
use Drago\Permission\WriteAccess;

class ArticleControl extends Nette\Application\UI\Control
{
	use WriteAccess;
}
```

```php
$grid->addActionEdit(fn(int $id) => $this->handleEdit($id))
	->setVisible($this->isWriteAllowed());
```

Skrytí tlačítka je jen pro pohodlí; samotný signál dál kontroluje trait `Authorization`.
