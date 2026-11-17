<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Resolver;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use Jul6Art\SettingBundle\Exception\UnknownSettingException;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads a setting in a context: the most precise level that sets it wins, the code's default otherwise.
 *
 * The values are cached by scope — one cache item per organisation, per app, per account, and the global one — so a
 * write invalidates its own scope and nothing else. A reading costs no query when the cache is warm, and one query for
 * all the scopes it misses otherwise; within a request, a scope is read from the cache once.
 *
 * A stored value outside its declaration — written before the bounds narrowed — is ignored: the next level up applies.
 *
 * @phpstan-import-type SettingValue from SettingDefinition
 */
final class SettingResolver implements ResetInterface
{
    /** @var array<string, array<string, mixed>> by scope id, then by key */
    private array $memo = [];

    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly SettingLevels $levels,
        private readonly SettingStoreInterface $store,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return SettingValue
     *
     * @throws UnknownSettingException
     */
    public function get(string $key, SettingContext $context): bool|int|string|array
    {
        return $this->resolve($key, $context)->value();
    }

    /**
     * @throws UnknownSettingException
     */
    public function resolve(string $key, SettingContext $context): ResolvedSetting
    {
        $definition = $this->registry->get($key);
        $scopes = $this->levels->scopesOf($context);

        return $this->resolved($definition, $scopes, $this->valuesOf($scopes));
    }

    /**
     * Every declared setting in a context — those a filter keeps, when one is given.
     *
     * @param (callable(SettingDefinition): bool)|null $filter
     *
     * @return list<ResolvedSetting>
     */
    public function resolveAll(SettingContext $context, ?callable $filter = null): array
    {
        $scopes = $this->levels->scopesOf($context);
        $values = $this->valuesOf($scopes);
        $resolved = [];
        foreach ($this->registry->all() as $definition) {
            if (null === $filter || $filter($definition)) {
                $resolved[] = $this->resolved($definition, $scopes, $values);
            }
        }

        return $resolved;
    }

    /**
     * Drops what is kept of a scope, here and in the cache: its values changed.
     */
    public function forget(Scope $scope): void
    {
        unset($this->memo[$scope->id()]);
        $this->cache->deleteItem(self::cacheKey($scope));
    }

    #[\Override]
    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * @param list<Scope>                         $scopes
     * @param array<string, array<string, mixed>> $values
     */
    private function resolved(SettingDefinition $definition, array $scopes, array $values): ResolvedSetting
    {
        $set = [];
        foreach ($scopes as $scope) {
            if (!$definition->opens($scope->level) || !\array_key_exists($definition->key, $values[$scope->id()] ?? [])) {
                continue;
            }
            try {
                $set[$scope->level] = $definition->normalize($values[$scope->id()][$definition->key]);
            } catch (InvalidSettingValueException) {
                // Written before the declaration narrowed: the next level up applies.
            }
        }

        return new ResolvedSetting($definition, $set);
    }

    /**
     * @param list<Scope> $scopes
     *
     * @return array<string, array<string, mixed>> by scope id, then by key
     */
    private function valuesOf(array $scopes): array
    {
        $missing = [];
        foreach ($scopes as $scope) {
            if (!\array_key_exists($scope->id(), $this->memo)) {
                $missing[self::cacheKey($scope)] = $scope;
            }
        }

        if ([] !== $missing) {
            $cold = [];
            foreach ($missing as $cacheKey => $scope) {
                $cached = $this->cache->getItem($cacheKey);
                $values = $cached->isHit() ? $cached->get() : null;
                if (\is_array($values)) {
                    $this->memo[$scope->id()] = self::byKey($values);
                } else {
                    $cold[] = $scope;
                }
            }

            if ([] !== $cold) {
                $loaded = $this->store->load($cold);
                foreach ($cold as $scope) {
                    $this->memo[$scope->id()] = $loaded[$scope->id()] ?? [];
                    $this->cache->saveDeferred($this->cache->getItem(self::cacheKey($scope))->set($this->memo[$scope->id()]));
                }
                $this->cache->commit();
            }
        }

        $values = [];
        foreach ($scopes as $scope) {
            $values[$scope->id()] = $this->memo[$scope->id()];
        }

        return $values;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function byKey(array $values): array
    {
        return array_filter($values, \is_string(...), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * A scope's subject can hold any character; a cache key cannot hold some ({}()/\@:).
     */
    private static function cacheKey(Scope $scope): string
    {
        return 'setting.'.hash('xxh128', $scope->id());
    }
}
