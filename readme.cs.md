# Drago Permission

Malá správa ACL a rolí.
Balíček poskytuje výchozí role, ACL zapsané v neonu vedle kódu, který chrání,
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

- Výchozí role: guest, user, admin
- ACL v neonových souborech, jeden pro každý modul
- Automatická autorizace presenterů
- Privilegia pojmenovaná podle operace (`default`, `edit`, `delete`), kde je potřeba, nastavená atributem `Access`
- Funkce Latte `isAllowed()` pro menu a odkazy
- Panel pro přepínání rolí v Tracy

## Role

Balíček obsahuje `role.acl.neon` s výchozími rolemi; administrátor smí všechno:

- guest
- user (dědí z guest)
- admin (dědí z user)

[drago-ex/project-tools](https://github.com/drago-ex/project-tools) ho zkopíruje do projektu jako
`app/Core/Security/role.acl.neon`, kde ho Nette načte s ostatními konfiguračními soubory z `app/`. Pozdější aktualizace
vaše úpravy zachová. Bez project-tools tam zkopírujte `resources/app/Core/Security/role.acl.neon` tohoto balíčku.

Názvy rolí jsou k dispozici také jako konstanty `Drago\Permission\Role`.

## ACL modulů

Pravidla presenteru jsou v `acl.neon` v jeho adresáři, vedle jeho `conf.neon`, v sekci `security` Nette.
Nette soubory všech modulů sloučí do jedné služby `Nette\Security\Permission`, takže není potřeba žádný kód v PHP.

Zdrojem je název presenteru. Pravidlo je potřeba jen pro jiné role než administrátora, například
pro veřejné stránky pro hosty:

```neon
# app/Presentation/Backend/Blog/acl.neon
security:
	resources:
		Backend:Blog:
```

```neon
# app/Presentation/Front/Blog/acl.neon
security:
	resources:
		Front:Blog:

	rules:
		allow:
			- [guest, Front:Blog]
```

Nový presenter potřebuje svůj zdroj (a pravidlo pro hosty, pokud je veřejný) v `acl.neon` vedle sebe.
Presenter bez zdroje se zamítne, viz níže.

## Autorizace presenterů

Autorizaci obstarává trait `Authorization`. Připojte ho do základního presenteru:

```php
use Drago\Permission\Authorization;

class BasePresenter extends Nette\Application\UI\Presenter
{
	use Authorization;
}
```

Stejně jako v [Nette ACL](https://doc.nette.org/cs/security/acl) je zdroj to, co se chrání (presenter),
a privilegium je operace:

| Kdy                                        | Privilegium                                              |
|--------------------------------------------|----------------------------------------------------------|
| při startu, u každého požadavku            | akce: `default`, `comments`, `edit`                      |
| signál, těsně před spuštěním jeho handleru | signál: `delete!` → `delete`, `approve!` → `approve`     |

Signál potřebuje i svou stránku: mazání na `Blog:default` potřebuje `default` a `delete`. Pravidla se pak
čtou jako aplikace:

```neon
security:
	roles:
		editor: [user]
	rules:
		allow:
			- [editor, Backend:Blog, [default, comments, edit]]   # smí upravovat, ne mazat
```

Neoprávněný přístup:
- nepřihlášený: přesměrování na `:Sign:in` s backlinkem (cíl změníte přepsáním `signInDestination()`); uloží se jen
  obyčejná GET stránka, signál ani formulář se po přihlášení nezopakuje
- přihlášený bez oprávnění: HTTP 403
- presenter bez registrovaného zdroje ACL se zamítne stejně, takže zapomenutý zdroj nikdy stránku neotevře

### Atribut Access

Signál bez atributu potřebuje privilegium se stejným názvem, formulář privilegium s názvem formuláře
(formulář `delete` → `delete`). Atribut `Access` to změní u handleru, nebo na továrně `createComponent<Name>()`
či třídě komponenty pro všechny její signály a podkomponenty (např. její formulář):

```php
use Drago\Permission\Access;

class ArticleControl extends Nette\Application\UI\Control
{
	public function handleDelete(int $id): void      // delete
	{
	}


	#[Access('edit')]
	public function handleRemoveImage(int $id): void // edit, součást úprav
	{
	}


	#[Access]
	public function handlePreview(): void            // stačí stránka
	{
	}


	#[Access('edit')]
	protected function createComponentArticle(): Form // edit, jeho odeslání
	{
	}
}
```

`#[Access(parameter: 'name')]` vezme privilegium z parametru signálu. Signál se kontroluje, když už jeho komponenta
existuje, takže rozhoduje atribut skutečné třídy bez ohledu na název komponenty.
[drago-ex/datagrid](https://github.com/drago-ex/datagrid) ho takto používá: řazení, filtry, stránkování a počet řádků
potřebují jen stránku a akce řádku potřebuje privilegium se jménem akce (`addActionEdit()` → `edit`,
`addActionDelete()` → `delete`).

Atribut za běhu nic nepotřebuje: PHP atribut ignoruje, když jeho třída není nainstalovaná, takže jakákoli komponenta
nebo balíček pro Nette může označit své signály a funguje i bez tohoto balíčku.

## Tlačítka

Trait `AccessCheck` umožní komponentě zobrazit tlačítka jen těm, kdo je smí použít. `isAllowed()` se ptá na
privilegium nad presenterem komponenty, stejné, jaké trait `Authorization` kontroluje pro signál:

```php
use Drago\Permission\AccessCheck;

class ArticleControl extends Nette\Application\UI\Control
{
	use AccessCheck;
}
```

```php
$grid->addActionDelete(fn(int $id) => $this->handleDelete($id))
	->setVisible($this->isAllowed('delete'));
```

```latte
<a n:if="$control->isAllowed('add')" n:href="add!" class="btn btn-primary btn-sm">Add</a>
```

Skrytí tlačítka je jen pro pohodlí; samotný signál dál kontroluje trait `Authorization`.

## Kontrola na dalších místech

Pro položku menu nebo odkaz na jiný presenter vrací `Privilege::isAllowed()` true, když má uživatel aspoň jedno
z privilegií nad zdrojem. Zdroj nebo role, které ACL nezná, se zamítnou místo vyhození výjimky:

```php
use Drago\Permission\Privilege;

Privilege::isAllowed($this->getUser(), 'Backend:Permission', 'default', 'users');
```

V šablonách zaregistrujte rozšíření Latte (vyžaduje `latte/latte`):

```neon
latte:
	extensions:
		- Drago\Permission\Latte\PermissionExtension
```

a použijte funkci `isAllowed()` se stejnými argumenty:

```latte
<li n:if="isAllowed('Backend:Permission', 'default', 'users')">
	<a n:href=":Backend:Permission:">Permissions</a>
</li>
```

## Privilegia presenteru

`PrivilegeCatalog` přečte privilegia presenteru z jeho kódu podle stejných pravidel: stránky (`action*()`,
`render*()` a šablony vedle něj) a signály a formuláře presenteru a jeho komponent. Signály, kterým stačí stránka,
vynechá. Administrace oprávnění je tak nabídne bez seznamu udržovaného ručně:

```php
$catalog = new Drago\Permission\PrivilegeCatalog($presenterFactory);
$catalog->getPrivileges('Backend:Blog'); // ['default', 'categories', 'delete', 'edit', …]
```

## Přepínání rolí v Tracy

Při vývoji umí Tracy bar přepnout role přihlášeného uživatele, takže aplikaci vyzkoušíte za jinou roli bez
testovacího účtu. Zaregistrujte panel (vyžaduje `tracy/tracy`):

```neon
tracy:
	bar:
		- Drago\Permission\Tracy\RoleSwitchPanel
```

Záložka ukazuje aktuální role (červeně, když jsou přepnuté). Panel nabízí role z ACL, z neonu
i z databáze; **My roles** vrátí skutečné role a odhlášení přepnutí zapomene. Nette přidává panely do baru jen
v ladicím režimu, takže v produkci panel nikdy neběží.

---

## Volitelné doplňky

Balíček funguje i bez nich. Pokud je projekt potřebuje, nastavte je ručně podle popisu níže.

### Související balíček: dynamická správa rolí

Pro dynamickou správu rolí a přístupů použijte:

- `drago-ex/project-permission`: https://github.com/drago-ex/project-permission

Tento balíček je postavený na `drago-ex/permission` a poskytuje:

- vytváření rolí
- přiřazování rolí uživatelům
- povolení nebo zakázání přístupu pro jednotlivé role
