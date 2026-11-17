<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Jul6Art\SettingBundle\Scope\Scope;

/**
 * Where the reworded messages live. The Doctrine store ships with the bundle (`setting.translations.override_class`).
 */
interface TranslationOverrideStoreInterface
{
    /**
     * Every message reworded at these scopes, in a language, in one reading.
     *
     * @param list<Scope> $scopes
     *
     * @return array<string, array<string, array<string, string>>> by scope id, then by domain, then by key; a scope with
     *                                                             none is left out
     */
    public function load(array $scopes, string $locale): array;

    public function save(Scope $scope, string $domain, string $locale, string $key, string $message, ?string $by): void;

    public function remove(Scope $scope, string $domain, string $locale, string $key): void;
}
