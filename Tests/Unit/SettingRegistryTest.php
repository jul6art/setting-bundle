<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Unit;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingDefinitionProviderInterface;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Definition\SettingType;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A provider may declare from data — the settings an app sends with its manifest —: the registry keeps what it collected
 * for a request, and collects again after it.
 */
#[CoversClass(SettingRegistry::class)]
final class SettingRegistryTest extends TestCase
{
    public function testItCollectsAgainAfterAReset(): void
    {
        $provider = new class implements SettingDefinitionProviderInterface {
            /** @var list<string> */
            public array $keys = ['shop.max_quantity'];

            #[\Override]
            public function definitions(): iterable
            {
                foreach ($this->keys as $key) {
                    yield new SettingDefinition($key, SettingType::Int, 20);
                }
            }
        };
        $registry = new SettingRegistry([$provider], new SettingLevels([]));

        self::assertSame(['shop.max_quantity'], array_keys($registry->all()));

        // An app declared one more: the request under way keeps what it read.
        $provider->keys[] = 'shop.min_quantity';
        self::assertFalse($registry->has('shop.min_quantity'));

        $registry->reset();
        self::assertTrue($registry->has('shop.min_quantity'));
    }
}
