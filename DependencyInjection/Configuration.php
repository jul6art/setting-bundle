<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\DependencyInjection;

use Jul6Art\SettingBundle\Scope\Scope;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The bundle's configuration tree.
 *
 * Write an `->info()` on every node: it is what `config:dump-reference` shows, and it is the only
 * documentation a reader gets before opening the code.
 *
 * > ⚠️ **A node that decides something at compile time cannot be an env var.** `%env(bool:X)%`
 * > reaches a `booleanNode()` as the placeholder *string* and the config layer rejects it. Use a
 * > plain value for anything that gates service registration, and keep env vars for values passed
 * > through to a service at runtime (a `scalarNode` argument).
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setting');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Registers the bundle\'s services. false leaves it installed and inert.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('levels')
                    ->info('The levels a value can be set at, from the broadest to the most precise, after the global one — organization, app, user. The most precise wins.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $level): bool => !\is_string($level) || 1 !== preg_match('/^[a-z][a-z0-9_]*$/', $level) || Scope::GLOBAL === $level)
                            ->thenInvalid('A level is a lowercase word, and not "global", which is always the first: %s')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $levels): bool => \count($levels) !== \count(array_unique(array_filter($levels, \is_string(...)))))
                        ->thenInvalid('A level is declared once: %s')
                    ->end()
                ->end()
                ->scalarNode('cache_pool')
                    ->info('The PSR-6 cache pool that keeps the values of each scope until one of them changes.')
                    ->defaultValue('cache.app')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('translations')
                    ->info('Messages reworded at a level, over the shipped files: the translator decorated. Without override_class, nothing.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('override_class')
                            ->info('The application\'s entity extending Jul6Art\SettingBundle\Translation\AbstractTranslationOverride.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('locales')
                            ->info('The languages a message may be reworded in.')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('storage')
                    ->info('The values in the application\'s table, through Doctrine. Without it, the application registers its own SettingStoreInterface.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('value_class')
                            ->info('The application\'s entity extending Jul6Art\SettingBundle\Doctrine\AbstractSettingValue.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
