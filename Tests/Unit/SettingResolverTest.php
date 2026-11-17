<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Unit;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Event\SettingChanged;
use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use Jul6Art\SettingBundle\Resolver\ResolvedSetting;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Store\InMemorySettingStore;
use Jul6Art\SettingBundle\Tests\Fixtures\RecordingDispatcher;
use Jul6Art\SettingBundle\Tests\Fixtures\TestSettings;
use Jul6Art\SettingBundle\Writer\SettingWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The cascade — global, organisation, app, account: the most precise value wins — its cache by scope, and the writer
 * that changes a value and drops its scope's cache.
 */
#[CoversClass(SettingResolver::class)]
#[CoversClass(SettingWriter::class)]
#[CoversClass(SettingRegistry::class)]
#[CoversClass(SettingLevels::class)]
#[CoversClass(ResolvedSetting::class)]
final class SettingResolverTest extends TestCase
{
    private InMemorySettingStore $store;

    private ArrayAdapter $cache;

    private SettingLevels $levels;

    private SettingRegistry $registry;

    private RecordingDispatcher $dispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->store = new InMemorySettingStore();
        $this->cache = new ArrayAdapter();
        $this->levels = new SettingLevels(['organization', 'app', 'user']);
        $this->registry = new SettingRegistry([new TestSettings()], $this->levels);
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testTheMostPreciseLevelWins(): void
    {
        $writer = $this->writer();
        $context = SettingContext::of(['organization' => '1', 'app' => 'todo', 'user' => '7']);

        self::assertSame(7, $this->resolver()->get(TestSettings::LIFETIME, $context));

        $writer->set(TestSettings::LIFETIME, Scope::global(), 10);
        $writer->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14);
        $writer->set(TestSettings::LIFETIME, new Scope('app', 'todo'), 21);
        $resolved = $this->resolver()->resolve(TestSettings::LIFETIME, $context);
        self::assertSame(21, $resolved->value());
        self::assertSame('app', $resolved->source());
        self::assertSame(['global' => 10, 'organization' => 14, 'app' => 21], $resolved->values);

        // Another organisation, another app: the global value.
        self::assertSame(10, $this->resolver()->get(TestSettings::LIFETIME, SettingContext::of(['organization' => '2', 'app' => 'crm'])));
    }

    public function testALevelTheSettingDoesNotOpenIsIgnored(): void
    {
        // Written behind the declaration's back, straight into the store: an account cannot override the lifetime.
        $this->store->save(TestSettings::LIFETIME, new Scope('user', '7'), 3, null);

        self::assertSame(7, $this->resolver()->get(TestSettings::LIFETIME, SettingContext::of(['user' => '7'])));
    }

    public function testAStoredValueOutsideItsDeclarationLetsTheLevelAboveApply(): void
    {
        $this->store->save(TestSettings::LIFETIME, Scope::global(), 12, null);
        $this->store->save(TestSettings::LIFETIME, new Scope('organization', '1'), 365, null);

        $resolved = $this->resolver()->resolve(TestSettings::LIFETIME, SettingContext::of(['organization' => '1']));
        self::assertSame(12, $resolved->value());
        self::assertSame('global', $resolved->source());
    }

    public function testAResetGivesWhatTheLevelAboveSets(): void
    {
        $writer = $this->writer();
        $writer->set(TestSettings::LIFETIME, Scope::global(), 10);
        $writer->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14);
        $resolved = $this->resolver()->resolve(TestSettings::LIFETIME, SettingContext::of(['organization' => '1']));

        self::assertSame(10, $resolved->above('organization')->value());
        self::assertSame('global', $resolved->above('organization')->source());
        self::assertSame(7, $resolved->above('global')->value());
        self::assertNull($resolved->above('global')->source());

        $writer->reset(TestSettings::LIFETIME, new Scope('organization', '1'));
        self::assertSame(10, $this->resolver()->get(TestSettings::LIFETIME, SettingContext::of(['organization' => '1'])));
    }

    public function testAWarmCacheReadsNoStore(): void
    {
        $context = SettingContext::of(['organization' => '1', 'app' => 'todo']);
        $this->resolver()->resolveAll($context);
        self::assertSame(1, $this->store->loads, 'One reading for the three scopes.');

        // Another request: a new resolver, the same cache.
        $this->resolver()->get(TestSettings::LIFETIME, $context);
        $this->resolver()->get(TestSettings::FEATURE, $context);
        self::assertSame(1, $this->store->loads);

        // Within a request, the cache itself is read once per scope.
        $resolver = $this->resolver();
        $resolver->get(TestSettings::LIFETIME, $context);
        $this->cache->clear();
        $resolver->get(TestSettings::LIFETIME, $context);
        self::assertSame(1, $this->store->loads);
    }

    public function testAWriteDropsItsScopeAndNoOther(): void
    {
        $context = SettingContext::of(['organization' => '1']);
        $resolver = $this->resolver();
        $writer = new SettingWriter($this->registry, $this->levels, $this->store, $resolver);
        self::assertSame(7, $resolver->get(TestSettings::LIFETIME, $context));
        $loads = $this->store->loads;

        $writer->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14);

        self::assertSame(14, $resolver->get(TestSettings::LIFETIME, $context));
        self::assertSame(14, $this->resolver()->get(TestSettings::LIFETIME, $context), 'The cache was dropped too.');
        // The writer read the scope's value before (1), the resolver read the organisation again (1); the global scope
        // stayed cached.
        self::assertSame($loads + 2, $this->store->loads);
    }

    public function testAChangeIsDispatchedOnlyWhenSomethingChanged(): void
    {
        $writer = $this->writer();

        $first = $writer->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14, 'admin@example.org');
        $writer->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14);
        $writer->reset(TestSettings::LIFETIME, new Scope('organization', '1'));
        $writer->reset(TestSettings::LIFETIME, new Scope('organization', '1'));

        self::assertNull($first->before);
        self::assertSame(14, $first->after);
        self::assertSame('admin@example.org', $first->by);
        self::assertEquals([
            new SettingChanged(TestSettings::LIFETIME, new Scope('organization', '1'), null, 14, 'admin@example.org'),
            new SettingChanged(TestSettings::LIFETIME, new Scope('organization', '1'), 14, null, null),
        ], $this->dispatcher->events);
    }

    public function testAValueIsCheckedBeforeItIsStored(): void
    {
        $this->expectExceptionObject(new InvalidSettingValueException(TestSettings::LIFETIME, InvalidSettingValueException::MAX, 30));

        $this->writer()->set(TestSettings::LIFETIME, new Scope('organization', '1'), 31);
    }

    public function testALevelTheSettingDoesNotOpenIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidSettingValueException(TestSettings::LIFETIME, InvalidSettingValueException::LEVEL));

        $this->writer()->set(TestSettings::LIFETIME, new Scope('user', '7'), 3);
    }

    public function testAContextCannotNameALevelTheApplicationDoesNotDeclare(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver()->get(TestSettings::LIFETIME, SettingContext::of(['team' => '3']));
    }

    public function testAFilterKeepsSomeSettings(): void
    {
        $resolved = $this->resolver()->resolveAll(SettingContext::global(), static fn (SettingDefinition $definition): bool => $definition->opens('user'));

        self::assertSame([TestSettings::FEATURE], array_map(static fn (ResolvedSetting $setting): string => $setting->definition->key, $resolved));
    }

    public function testASettingDeclaredTwiceIsAMistakeOfCode(): void
    {
        $this->expectException(\LogicException::class);

        new SettingRegistry([new TestSettings(), new TestSettings()], $this->levels)->all();
    }

    public function testASettingOpeningALevelTheApplicationDoesNotDeclareIsAMistakeOfCode(): void
    {
        $this->expectException(\LogicException::class);

        new SettingRegistry([new TestSettings()], new SettingLevels(['organization']))->all();
    }

    private function resolver(): SettingResolver
    {
        return new SettingResolver($this->registry, $this->levels, $this->store, $this->cache);
    }

    private function writer(): SettingWriter
    {
        return new SettingWriter($this->registry, $this->levels, $this->store, $this->resolver(), $this->dispatcher);
    }
}
