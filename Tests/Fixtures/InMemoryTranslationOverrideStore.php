<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures;

use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Translation\TranslationOverrideStoreInterface;

/**
 * The reworded messages in memory, with the readings counted.
 */
final class InMemoryTranslationOverrideStore implements TranslationOverrideStoreInterface
{
    /** @var array<string, array<string, array<string, array<string, string>>>> by locale, scope id, domain, key */
    private array $messages = [];

    public int $loads = 0;

    #[\Override]
    public function load(array $scopes, string $locale): array
    {
        ++$this->loads;
        $found = [];
        foreach ($scopes as $scope) {
            if (isset($this->messages[$locale][$scope->id()])) {
                $found[$scope->id()] = $this->messages[$locale][$scope->id()];
            }
        }

        return $found;
    }

    #[\Override]
    public function save(Scope $scope, string $domain, string $locale, string $key, string $message, ?string $by): void
    {
        $this->messages[$locale][$scope->id()][$domain][$key] = $message;
    }

    #[\Override]
    public function remove(Scope $scope, string $domain, string $locale, string $key): void
    {
        unset($this->messages[$locale][$scope->id()][$domain][$key]);
    }
}
