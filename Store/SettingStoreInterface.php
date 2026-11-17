<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Store;

use Jul6Art\SettingBundle\Scope\Scope;

/**
 * Where the values live. The Doctrine store ships with the bundle (`setting.storage.value_class`); an application that
 * keeps its values elsewhere — a satellite reading them from the platform — registers its own.
 *
 * The store keeps values as they were written; the resolver checks them against their declaration at reading.
 */
interface SettingStoreInterface
{
    /**
     * Every value set at these scopes, in one reading.
     *
     * @param list<Scope> $scopes
     *
     * @return array<string, array<string, mixed>> by scope id ({@see Scope::id()}), then by key; a scope with no value
     *                                             is left out
     */
    public function load(array $scopes): array;

    public function save(string $key, Scope $scope, mixed $value, ?string $by): void;

    public function remove(string $key, Scope $scope): void;

    /**
     * Every value of a scope gone — its subject was deleted.
     */
    public function removeScope(Scope $scope): void;
}
