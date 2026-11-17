<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Jul6Art\SettingBundle\Scope\Scope;

/**
 * A message reworded, or given back to the shipped text, at a scope — dispatched once the store has it.
 */
final readonly class TranslationOverrideChanged
{
    public function __construct(
        public Scope $scope,
        public string $domain,
        public string $locale,
        public string $key,
        public ?string $before,
        public ?string $after,
        public ?string $by,
    ) {
    }
}
