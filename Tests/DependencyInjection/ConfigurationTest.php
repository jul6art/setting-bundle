<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\DependencyInjection;

use Jul6Art\SettingBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The configuration tree is public API: an application writes against it and a rename breaks
 * someone's deployment. Assert the **whole** processed shape rather than one key at a time — that
 * is what makes an accidental addition or a changed default visible in a diff.
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    private const array DEFAULTS = ['enabled' => true, 'levels' => [], 'cache_pool' => 'cache.app', 'translations' => ['override_class' => null, 'locales' => []], 'storage' => ['value_class' => null]];

    public function testItsRootNodeIsTheBundleAlias(): void
    {
        self::assertSame('setting', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    public function testItAppliesItsDefaults(): void
    {
        self::assertSame(self::DEFAULTS, $this->process([]));
    }

    public function testLaterConfigsOverrideEarlierOnes(): void
    {
        self::assertSame(self::DEFAULTS, $this->process([['enabled' => false], ['enabled' => true]]));
    }

    public function testItKeepsTheLevelsInTheirOrder(): void
    {
        self::assertSame(
            ['levels' => ['organization', 'app', 'user'], 'cache_pool' => 'cache.settings', 'storage' => ['value_class' => 'App\Entity\SettingValue'], 'enabled' => true, 'translations' => ['override_class' => null, 'locales' => []]],
            $this->process([['levels' => ['organization', 'app', 'user'], 'cache_pool' => 'cache.settings', 'storage' => ['value_class' => 'App\Entity\SettingValue']]]),
        );
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function wrongLevels(): iterable
    {
        yield 'the global level, always first' => [['global', 'organization']];
        yield 'a level twice' => [['organization', 'organization']];
        yield 'not a lowercase word' => [['Organization']];
    }

    /**
     * @param list<string> $levels
     */
    #[DataProvider('wrongLevels')]
    public function testItRefusesWrongLevels(array $levels): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['levels' => $levels]]);
    }

    /**
     * A `booleanNode` refuses anything but a boolean, which is what you want — and the reason an
     * env var cannot gate service registration.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanValues(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['enabled' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [0];
        yield 'array' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
