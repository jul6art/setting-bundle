<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Unit;

use Jul6Art\AclBundle\Contract\AclTenantInterface;
use Jul6Art\AclBundle\Contract\AclUserInterface;
use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingDefinitionProviderInterface;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Definition\SettingType;
use Jul6Art\SettingBundle\Feature\Features;
use Jul6Art\SettingBundle\Feature\SettingFeatureChecker;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Store\InMemorySettingStore;
use Jul6Art\SettingBundle\Writer\SettingWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A feature flag is a boolean setting under `feature.`: off when nobody declared it, switched in the cascade like any
 * setting — and read by acl-bundle's gate through the user's tenant.
 */
#[CoversClass(Features::class)]
#[CoversClass(SettingFeatureChecker::class)]
final class FeaturesTest extends TestCase
{
    private InMemorySettingStore $store;

    private SettingRegistry $registry;

    private SettingResolver $resolver;

    private SettingLevels $levels;

    #[\Override]
    protected function setUp(): void
    {
        $this->store = new InMemorySettingStore();
        $this->levels = new SettingLevels(['organization']);
        $this->registry = new SettingRegistry([new class implements SettingDefinitionProviderInterface {
            #[\Override]
            public function definitions(): iterable
            {
                yield Features::define('reports', true, ['organization'], 'feature.reports');
                yield Features::define('exports');
                // Under the prefix, but no flag: never read as one.
                yield new SettingDefinition('feature.limit', SettingType::Int, 3);
            }
        }], $this->levels);
        $this->resolver = new SettingResolver($this->registry, $this->levels, $this->store, new ArrayAdapter());
    }

    public function testAFlagIsABooleanSettingUnderItsPrefix(): void
    {
        $definition = Features::define('reports', true, ['organization'], 'feature.reports');

        self::assertSame('feature.reports', $definition->key);
        self::assertSame(SettingType::Bool, $definition->type);
        self::assertTrue($definition->default);
        self::assertSame('features', $definition->group);
        self::assertSame('feature.reports', Features::key('reports'));
    }

    public function testAFlagIsSwitchedInTheCascade(): void
    {
        $features = new Features($this->registry, $this->resolver);
        $acme = SettingContext::of(['organization' => '1']);
        $globex = SettingContext::of(['organization' => '2']);

        self::assertTrue($features->isEnabled('reports', $acme));
        self::assertFalse($features->isEnabled('exports', $acme));

        $this->writer()->set('feature.reports', new Scope('organization', '1'), false);
        $this->writer()->set('feature.exports', Scope::global(), true);

        self::assertFalse($features->isEnabled('reports', $acme));
        self::assertTrue($features->isEnabled('reports', $globex));
        self::assertTrue($features->isEnabled('exports', $globex));
    }

    public function testAFlagNobodyDeclaredIsOff(): void
    {
        $features = new Features($this->registry, $this->resolver);

        self::assertFalse($features->isEnabled('billing', SettingContext::of([])));
        self::assertFalse($features->isEnabled('limit', SettingContext::of([])));
    }

    public function testAclBundlesGateReadsTheFlagOfTheUsersTenant(): void
    {
        $checker = new SettingFeatureChecker(new Features($this->registry, $this->resolver), 'organization');
        $this->writer()->set('feature.reports', new Scope('organization', '1'), false);

        self::assertFalse($checker->isEnabled(self::user(1), 'reports'));
        self::assertTrue($checker->isEnabled(self::user(2), 'reports'));
        // No tenant, no feature.
        self::assertFalse($checker->isEnabled(self::user(null), 'reports'));
    }

    private function writer(): SettingWriter
    {
        return new SettingWriter($this->registry, $this->levels, $this->store, $this->resolver);
    }

    private static function user(?int $tenant): AclUserInterface
    {
        return new readonly class($tenant) implements AclUserInterface {
            public function __construct(
                private ?int $tenant,
            ) {
            }

            #[\Override]
            public function getId(): int
            {
                return 7;
            }

            #[\Override]
            public function getRoles(): array
            {
                return [];
            }

            #[\Override]
            public function isActive(): bool
            {
                return true;
            }

            #[\Override]
            public function isSuperAdmin(): bool
            {
                return false;
            }

            #[\Override]
            public function getTenant(): ?AclTenantInterface
            {
                return null === $this->tenant ? null : new readonly class($this->tenant) implements AclTenantInterface {
                    public function __construct(
                        private int $id,
                    ) {
                    }

                    #[\Override]
                    public function getId(): int
                    {
                        return $this->id;
                    }

                    #[\Override]
                    public function getSlug(): string
                    {
                        return 'tenant-'.$this->id;
                    }
                };
            }
        };
    }
}
