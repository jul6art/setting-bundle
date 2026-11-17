<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\DependencyInjection;

use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Settles, once every bundle is configured, that the values have somewhere to live: the Doctrine store
 * (`setting.storage.value_class`), or the application's own {@see SettingStoreInterface}. Without one, the container
 * would fail on a reference to a missing service — a message that does not say what to do.
 */
final class StorePass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('setting.enabled') || $container->has(SettingStoreInterface::class)) {
            return;
        }

        throw new \LogicException(\sprintf('setting-bundle keeps its values nowhere: set setting.storage.value_class to an entity extending AbstractSettingValue, or register a service for %s.', SettingStoreInterface::class));
    }
}
