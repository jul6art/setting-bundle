<p align="center">
    <a href="https://devinthehood.com/"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

Settings declared in code, overridden in cascade, changed hot
=============================================================

<p align="left">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v1&color=0ea5e9" alt="Version">
</p>

Settings declared in code, overridden in cascade, changed hot

Requirements
------------

- PHP ^8.5
- Symfony ^7.4 || ^8.0

Installation
------------

```shell
composer require jul6art/setting-bundle
```

Then register it in `config/bundles.php` (Flex does this for you):

```php
Jul6Art\SettingBundle\SettingBundle::class => ['all' => true],
```

Configuration
-------------

```yaml
# config/packages/setting.yaml
setting:
    # Leaves the bundle installed and inert when false.
    enabled: true
    # The levels a value can be set at, from the broadest to the most precise, after the global one.
    levels: [organization, app, user]
    # The PSR-6 pool that keeps the values of each scope until one of them changes.
    cache_pool: cache.app
    storage:
        # Your entity extending Jul6Art\SettingBundle\Doctrine\AbstractSettingValue.
        value_class: App\Entity\SettingValue
```

`setting.enabled` is also exposed as a container parameter.

Usage
-----

### 1. Declare a setting, in code

A setting exists only once declared: a key, a type, a default, its bounds, and the levels where it can be overridden.
There is no free key, and a value outside the declaration is refused.

```php
use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingDefinitionProviderInterface;
use Jul6Art\SettingBundle\Definition\SettingType;

final class InvitationSettings implements SettingDefinitionProviderInterface
{
    public const string LIFETIME = 'invitation.lifetime_days';

    public function definitions(): iterable
    {
        yield new SettingDefinition(self::LIFETIME, SettingType::Int, 7, ['organization'], min: 1, max: 30, unit: 'days', label: 'settings.invitation_lifetime');
    }
}
```

Every service implementing `SettingDefinitionProviderInterface` is collected (autoconfiguration). The types are `Bool`,
`Int` (with `min`, `max` and a `unit` — a duration is an integer of days or minutes), `Text` (`min` and `max` bound its
length), `Choice` and `List` (an ordered list of `choices`, each at most once — a menu's entries, in their order).

A key declared twice, a default outside its own bounds, or a level the application does not declare fails at the first
reading: they are mistakes of code, not of data.

### 2. Store the values

The values live in your own table: an entity extending `AbstractSettingValue`, named by `storage.value_class`. It
declares its table, its id and its unique constraint, and keeps the base class's constructor.

```php
#[ORM\Entity]
#[ORM\Table(name: 'setting_value')]
#[ORM\UniqueConstraint(columns: ['setting_key', 'level', 'subject'])]
#[ORM\Index(columns: ['level', 'subject'])]
class SettingValue extends AbstractSettingValue
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
}
```

Then generate its migration. An application keeping its values elsewhere — a satellite reading them from a platform —
registers a service for `SettingStoreInterface` instead; without either, the container refuses to build and says so.

### 3. Read a value in a context

```php
$days = $resolver->get(InvitationSettings::LIFETIME, SettingContext::of(['organization' => (string) $organization->getId()]));
```

`SettingResolver` reads the global level, then each level of the context, broadest first: the most precise value wins,
the code's default otherwise. `resolve()` says where the value comes from (`source()`), what each level sets (`at()`),
and what a reset would give (`above($level)`); `resolveAll()` does it for every declared setting — a settings screen.

The values are cached by scope: a reading costs no query when the cache is warm, and one query for every scope it
misses. A stored value outside its declaration — written before its bounds narrowed — is ignored, and the level above
applies.

### 4. Change a value

```php
$writer->set(InvitationSettings::LIFETIME, new Scope('organization', '42'), 14, 'admin@example.org');
$writer->reset(InvitationSettings::LIFETIME, new Scope('organization', '42'));
```

`SettingWriter` checks the value and the level, stores it, drops its scope's cache, and dispatches `SettingChanged`
(before, after, by whom) when something changed. **Who may write is the application's to decide** before calling it:
the bundle checks a value, not a permission. The Doctrine store flushes: a journal line the caller persisted just before
goes in the same transaction.

From the command line — the global level, the installation's own value, by default:

```shell
bin/console setting:set invitation.lifetime_days 14
bin/console setting:set reports.enabled true --level=app --subject=todo
bin/console setting:set invitation.lifetime_days --reset
```

### 5. Reword a message, over the shipped files

A message can be reworded at a level — for an organisation, an app — without a delivery: the translator is decorated,
and a key reworded for the context of the moment is translated with that text, formatted with its parameters as a
shipped message is. The files are never rewritten.

```yaml
setting:
    translations:
        override_class: App\Entity\TranslationOverride   # extends Jul6Art\SettingBundle\Translation\AbstractTranslationOverride
        locales: [fr, en]
```

```php
#[ORM\Entity]
#[ORM\Table(name: 'setting_translation')]
#[ORM\UniqueConstraint(columns: ['domain', 'locale', 'translation_key', 'level', 'subject'])]
#[ORM\Index(columns: ['level', 'subject', 'locale'])]
class TranslationOverride extends AbstractTranslationOverride { /* its id */ }
```

**Whose rewording applies is the application's to say**: it registers a `TranslationContextProviderInterface`, which
gives the context of the moment — the request's organisation —, or null (a command, a worker): the shipped messages
then. `TranslationOverrides` rewords and restores (`reword()`, `restore()`, `TranslationOverrideChanged` dispatched), and
gives a context's messages by domain (`messages()`) — what a front loads at startup. Cached by scope and language.

A reworded message is formatted by the framework's message formatter (`%name%` parameters), not as ICU.

### The traps

- **A subject deleted leaves its values behind** until you call `SettingStoreInterface::removeScope()`. It is a bulk
  DQL delete: no lifecycle event, listener or Doctrine-based audit sees those rows go.
- **A message rendered without a request is the shipped one**: an e-mail whose body a worker renders reads no context.
  Translate what must follow the rewording — a subject — where the request is.
- **A setting holds no secret and no personal data.** There is no secret type on purpose: a secret belongs in a vault.
- **A provider may declare from data** — the settings an app sends —: the registry collects again after each request
  or message (`kernel.reset`), never within one.
- **The cache pool must be shared by every process** that reads settings — a filesystem pool on one server, Redis
  across several — or a write invalidates one process's cache and not the others'.

Quality assurance
-----------------

```shell
composer qa            # cs-check + rector-check + phpstan (level max) + phpunit
```

Run `composer qa`, not the single tool you have in mind: the CI's "Coding standards" job runs
Rector too, and its `lowest deps` job installs the minimum of every constraint — which is where
this ecosystem has repeatedly found what a local run could not.

`extra.symfony.require` states which Symfony line this bundle targets; the CI enforces it with
`SYMFONY_REQUIRE` on both the highest and the lowest job. A local `composer install` may still
resolve a newer Symfony, which broadens what you exercise rather than narrowing it — but it means
the toolchain can propose something that only makes sense on one branch. `rector.php` skips one
such rule already, with the reason written next to it.

Whatever you do, keep the code free of classes that exist on only one of the declared branches.
A bundle promising `^7.4 || ^8.0` has to hold both.

License
-------

Settings declared in code, overridden in cascade, changed hot is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [Jul6Art](https://devinthehood.com/)
