<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Functional;

use Jul6Art\SettingBundle\Doctrine\DoctrineSettingStore;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Jul6Art\SettingBundle\Tests\Fixtures\Entity\SettingValue;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The first test to write, and the one that keeps paying: a real container, built with the bundle
 * registered.
 *
 * It catches what no unit test can — a services.yaml that does not parse, a reference to a service
 * that does not exist, a configuration node the extension reads under another name. Every one of
 * those is invisible until something boots.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    public function testTheBundleBootsOnDoctrine(): void
    {
        $container = $this->boot('test', ['levels' => ['organization'], 'storage' => ['value_class' => SettingValue::class]], withOrm: true);

        self::assertTrue($container->getParameter('setting.enabled'));
        self::assertInstanceOf(DoctrineSettingStore::class, $container->get(SettingStoreInterface::class));
    }

    /**
     * Without a store, the container says what to do, rather than failing on a missing reference.
     */
    public function testWithoutAStoreItSaysWhatToDo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('setting.storage.value_class');

        $this->boot();
    }

    /**
     * `enabled: false` must leave the bundle installed and inert — an application should be able
     * to switch it off without uninstalling it, and without its optional dependencies becoming
     * required.
     */
    public function testItCanBeDisabled(): void
    {
        self::assertFalse($this->boot('test', ['enabled' => false])->hasParameter('setting.enabled'));
    }
}
