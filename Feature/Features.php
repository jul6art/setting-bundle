<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Feature;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Definition\SettingType;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\SettingContext;

/**
 * Feature flags: what a tenant activated, not what a person may do. A flag is a boolean setting under `feature.` —
 * declared like any other, overridden in the same cascade, changed by the same writer —, so a flag the installation
 * switches off by default can be switched on for one organisation.
 *
 * A flag nobody declared is off: a gate that opens when its declaration is missing turns a paid module into a free one.
 */
final readonly class Features
{
    public const string PREFIX = 'feature.';

    public function __construct(
        private SettingRegistry $registry,
        private SettingResolver $resolver,
    ) {
    }

    /**
     * The setting behind the flag `reports`: `feature.reports`.
     */
    public static function key(string $code): string
    {
        return self::PREFIX.$code;
    }

    /**
     * A flag's declaration, for a provider: a boolean, off unless said otherwise.
     *
     * @param list<string> $levels where it can be switched, besides the global level
     */
    public static function define(string $code, bool $default = false, array $levels = [], ?string $label = null, ?string $group = 'features'): SettingDefinition
    {
        return new SettingDefinition(self::key($code), SettingType::Bool, $default, $levels, label: $label, group: $group);
    }

    public function isEnabled(string $code, SettingContext $context): bool
    {
        $key = self::key($code);
        if (!$this->registry->has($key) || SettingType::Bool !== $this->registry->get($key)->type) {
            return false;
        }

        return true === $this->resolver->get($key, $context);
    }
}
