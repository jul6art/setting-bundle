<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\DependencyInjection;

use Jul6Art\SettingBundle\Command\SettingSetCommand;
use Jul6Art\SettingBundle\Definition\SettingDefinitionProviderInterface;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Doctrine\DoctrineSettingStore;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Jul6Art\SettingBundle\Translation\DoctrineTranslationOverrideStore;
use Jul6Art\SettingBundle\Translation\OverridingTranslator;
use Jul6Art\SettingBundle\Translation\TranslationContextProviderInterface;
use Jul6Art\SettingBundle\Translation\TranslationOverrides;
use Jul6Art\SettingBundle\Translation\TranslationOverrideStoreInterface;
use Jul6Art\SettingBundle\Writer\SettingWriter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Wires the bundle's services and turns its configuration into something the services can read.
 *
 * Two rules this ecosystem arrived at the hard way:
 *
 * 1. **A brick whose dependency is optional is registered conditionally**, from here, guarded by
 *    `class_exists()` / `interface_exists()` — never by an attribute on the class. An
 *    `#[AsDecorator]` or `#[AsDoctrineListener]` on a vendor class is only honoured if the
 *    application autoconfigures `vendor/`, which it should not, and it makes the class
 *    unloadable when the package is absent.
 * 2. **A service that needs another *service* to exist is checked in a compiler pass**, not
 *    here: an extension runs before the other bundles have configured anything, so
 *    `$container->has('some.service')` is always false at this point.
 *
 * Here: the Doctrine store only when `storage.value_class` names an entity, the command only when the console is
 * installed. Whether the application registered a store of its own is a service question — {@see StorePass}.
 */
class SettingExtension extends Extension implements PrependExtensionInterface
{
    public const string PROVIDER_TAG = 'setting.definition_provider';

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        // Exposed as a container parameter so an application can branch on it, and so
        // `debug:container --parameter` tells the truth about what is active.
        $container->setParameter('setting.enabled', true);

        $container->registerForAutoconfiguration(SettingDefinitionProviderInterface::class)->addTag(self::PROVIDER_TAG);

        $container->register(SettingLevels::class)->setArgument('$levels', array_values(self::strings($config['levels'] ?? [])));
        $container->register(SettingRegistry::class)
            ->setArgument('$providers', new TaggedIteratorArgument(self::PROVIDER_TAG))
            ->setArgument('$levels', new Reference(SettingLevels::class))
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(SettingResolver::class)
            ->setArgument('$registry', new Reference(SettingRegistry::class))
            ->setArgument('$levels', new Reference(SettingLevels::class))
            ->setArgument('$store', new Reference(SettingStoreInterface::class))
            ->setArgument('$cache', new Reference(\is_string($config['cache_pool'] ?? null) ? $config['cache_pool'] : 'cache.app'))
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(SettingWriter::class)
            ->setArgument('$registry', new Reference(SettingRegistry::class))
            ->setArgument('$levels', new Reference(SettingLevels::class))
            ->setArgument('$store', new Reference(SettingStoreInterface::class))
            ->setArgument('$resolver', new Reference(SettingResolver::class))
            ->setArgument('$dispatcher', new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE));

        $storage = $config['storage'] ?? null;
        $valueClass = \is_array($storage) ? ($storage['value_class'] ?? null) : null;
        if (\is_string($valueClass) && '' !== $valueClass) {
            $container->register(DoctrineSettingStore::class)
                ->setArgument('$doctrine', new Reference('doctrine'))
                ->setArgument('$valueClass', $valueClass);
            $container->setAlias(SettingStoreInterface::class, DoctrineSettingStore::class);
        }

        $translations = $config['translations'] ?? null;
        $overrideClass = \is_array($translations) ? ($translations['override_class'] ?? null) : null;
        if (\is_string($overrideClass) && '' !== $overrideClass && interface_exists(TranslatorBagInterface::class)) {
            $container->register(DoctrineTranslationOverrideStore::class)
                ->setArgument('$doctrine', new Reference('doctrine'))
                ->setArgument('$overrideClass', $overrideClass);
            $container->setAlias(TranslationOverrideStoreInterface::class, DoctrineTranslationOverrideStore::class);
            $container->register(TranslationOverrides::class)
                ->setArgument('$levels', new Reference(SettingLevels::class))
                ->setArgument('$store', new Reference(TranslationOverrideStoreInterface::class))
                ->setArgument('$cache', new Reference(\is_string($config['cache_pool'] ?? null) ? $config['cache_pool'] : 'cache.app'))
                ->setArgument('$locales', array_values(self::strings(\is_array($translations) ? ($translations['locales'] ?? []) : [])))
                ->setArgument('$dispatcher', new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE))
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->register(OverridingTranslator::class)
                ->setDecoratedService('translator', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->setArgument('$translator', new Reference(OverridingTranslator::class.'.inner'))
                ->setArgument('$overrides', new Reference(TranslationOverrides::class))
                ->setArgument('$formatter', new Reference('translator.formatter'))
                ->setArgument('$contexts', new Reference(TranslationContextProviderInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        if (class_exists(Command::class)) {
            $container->register(SettingSetCommand::class)
                ->setArgument('$writer', new Reference(SettingWriter::class))
                ->addTag('console.command', ['command' => 'setting:set']);
        }
    }

    /**
     * Doctrine must see the mapped superclass — the bundle's `Doctrine/` is no `Entity/` directory that auto-mapping
     * finds. Prepended, so the application's own `doctrine` configuration keeps the last word.
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $mappings = [];
        foreach ($container->getExtensionConfig($this->getAlias()) as $config) {
            $storage = $config['storage'] ?? null;
            if (\is_array($storage) && \is_string($storage['value_class'] ?? null)) {
                $mappings['Jul6ArtSettingStorage'] = ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/../Doctrine', 'prefix' => 'Jul6Art\\SettingBundle\\Doctrine'];
            }
            $translations = $config['translations'] ?? null;
            if (\is_array($translations) && \is_string($translations['override_class'] ?? null)) {
                $mappings['Jul6ArtSettingTranslations'] = ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/../Translation', 'prefix' => 'Jul6Art\\SettingBundle\\Translation'];
            }
        }
        if ([] !== $mappings) {
            $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => $mappings]]);
        }
    }

    /**
     * @return array<array-key, string>
     */
    private static function strings(mixed $values): array
    {
        return \is_array($values) ? array_filter($values, \is_string(...)) : [];
    }
}
