<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The messages reworded for a context, in a language — the global level, then each level of the context, the most
 * precise winning for a key —, and the rewording itself.
 *
 * Cached by scope and language, as the settings are: a reading costs no query when the cache is warm, one for every
 * scope it misses; a rewording drops its own scope's entries, and nothing else.
 */
final class TranslationOverrides implements ResetInterface
{
    /** @var array<string, array<string, array<string, string>>> by scope id and language, then by domain and key */
    private array $memo = [];

    /**
     * @param list<string> $locales the languages a rewording may be made in, to drop each one's cache
     */
    public function __construct(
        private readonly SettingLevels $levels,
        private readonly TranslationOverrideStoreInterface $store,
        private readonly CacheItemPoolInterface $cache,
        private readonly array $locales = [],
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * The reworded messages of a context in a language, by domain then key.
     *
     * @return array<string, array<string, string>>
     */
    public function messages(SettingContext $context, string $locale): array
    {
        $merged = [];
        foreach ($this->byScope($this->levels->scopesOf($context), $locale) as $domains) {
            foreach ($domains as $domain => $messages) {
                $merged[$domain] = [...$merged[$domain] ?? [], ...$messages];
            }
        }

        return $merged;
    }

    public function message(SettingContext $context, string $domain, string $key, string $locale): ?string
    {
        return $this->messages($context, $locale)[$domain][$key] ?? null;
    }

    /**
     * The message reworded at a scope, if any — what removing it would take away.
     */
    public function at(Scope $scope, string $domain, string $key, string $locale): ?string
    {
        return $this->byScope([$scope], $locale)[$scope->id()][$domain][$key] ?? null;
    }

    public function reword(Scope $scope, string $domain, string $locale, string $key, string $message, ?string $by = null): TranslationOverrideChanged
    {
        $change = new TranslationOverrideChanged($scope, $domain, $locale, $key, $this->at($scope, $domain, $key, $locale), $message, $by);
        if ($change->before !== $message) {
            $this->store->save($scope, $domain, $locale, $key, $message, $by);
            $this->done($change);
        }

        return $change;
    }

    public function restore(Scope $scope, string $domain, string $locale, string $key, ?string $by = null): TranslationOverrideChanged
    {
        $change = new TranslationOverrideChanged($scope, $domain, $locale, $key, $this->at($scope, $domain, $key, $locale), null, $by);
        if (null !== $change->before) {
            $this->store->remove($scope, $domain, $locale, $key);
            $this->done($change);
        }

        return $change;
    }

    #[\Override]
    public function reset(): void
    {
        $this->memo = [];
    }

    private function done(TranslationOverrideChanged $change): void
    {
        foreach (array_unique([...$this->locales, $change->locale]) as $locale) {
            unset($this->memo[self::memoKey($change->scope, $locale)]);
            $this->cache->deleteItem(self::cacheKey($change->scope, $locale));
        }
        $this->dispatcher?->dispatch($change);
    }

    /**
     * @param list<Scope> $scopes
     *
     * @return array<string, array<string, array<string, string>>> by scope id, broadest first, then by domain and key
     */
    private function byScope(array $scopes, string $locale): array
    {
        $cold = [];
        foreach ($scopes as $scope) {
            if (\array_key_exists(self::memoKey($scope, $locale), $this->memo)) {
                continue;
            }
            $item = $this->cache->getItem(self::cacheKey($scope, $locale));
            $cached = $item->isHit() ? $item->get() : null;
            if (\is_array($cached)) {
                $this->memo[self::memoKey($scope, $locale)] = self::messagesOf($cached);
            } else {
                $cold[] = $scope;
            }
        }

        if ([] !== $cold) {
            $loaded = $this->store->load($cold, $locale);
            foreach ($cold as $scope) {
                $this->memo[self::memoKey($scope, $locale)] = $loaded[$scope->id()] ?? [];
                $this->cache->saveDeferred($this->cache->getItem(self::cacheKey($scope, $locale))->set($this->memo[self::memoKey($scope, $locale)]));
            }
            $this->cache->commit();
        }

        $found = [];
        foreach ($scopes as $scope) {
            $found[$scope->id()] = $this->memo[self::memoKey($scope, $locale)];
        }

        return $found;
    }

    /**
     * @param array<mixed> $cached
     *
     * @return array<string, array<string, string>>
     */
    private static function messagesOf(array $cached): array
    {
        $messages = [];
        foreach ($cached as $domain => $keys) {
            if (!\is_string($domain) || !\is_array($keys)) {
                continue;
            }
            foreach ($keys as $key => $message) {
                if (\is_string($key) && \is_string($message)) {
                    $messages[$domain][$key] = $message;
                }
            }
        }

        return $messages;
    }

    private static function memoKey(Scope $scope, string $locale): string
    {
        return $scope->id().'|'.$locale;
    }

    private static function cacheKey(Scope $scope, string $locale): string
    {
        return 'setting.translations.'.hash('xxh128', self::memoKey($scope, $locale));
    }
}
