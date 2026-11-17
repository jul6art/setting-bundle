<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Store;

use Jul6Art\SettingBundle\Scope\Scope;

/**
 * The values in memory, for a test — they last as long as the process.
 */
final class InMemorySettingStore implements SettingStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $values = [];

    /** How many readings reached it: what a test of the cache counts. */
    public int $loads = 0;

    #[\Override]
    public function load(array $scopes): array
    {
        ++$this->loads;
        $found = [];
        foreach ($scopes as $scope) {
            if (isset($this->values[$scope->id()])) {
                $found[$scope->id()] = $this->values[$scope->id()];
            }
        }

        return $found;
    }

    #[\Override]
    public function save(string $key, Scope $scope, mixed $value, ?string $by): void
    {
        $this->values[$scope->id()][$key] = $value;
    }

    #[\Override]
    public function remove(string $key, Scope $scope): void
    {
        unset($this->values[$scope->id()][$key]);
        if ([] === ($this->values[$scope->id()] ?? [])) {
            unset($this->values[$scope->id()]);
        }
    }

    #[\Override]
    public function removeScope(Scope $scope): void
    {
        unset($this->values[$scope->id()]);
    }
}
