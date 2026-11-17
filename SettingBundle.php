<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle;

use Jul6Art\SettingBundle\DependencyInjection\StorePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Settings declared in code, overridden in cascade, changed hot.
 *
 * {@see StorePass} settles, once every bundle is configured, that the values have a store.
 */
class SettingBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new StorePass());
    }
}
