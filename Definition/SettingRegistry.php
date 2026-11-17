<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Definition;

use Jul6Art\SettingBundle\Exception\UnknownSettingException;
use Jul6Art\SettingBundle\Scope\SettingLevels;

/**
 * Every declared setting, by key — collected once, on first use, from the providers.
 *
 * Two providers declaring the same key, or a setting opening a level the application does not declare, is a mistake of
 * code, not of data: it fails loudly, at the first reading.
 */
final class SettingRegistry
{
    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    /**
     * @param iterable<SettingDefinitionProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly SettingLevels $levels,
    ) {
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @throws UnknownSettingException
     */
    public function get(string $key): SettingDefinition
    {
        return $this->all()[$key] ?? throw new UnknownSettingException($key);
    }

    /**
     * @return array<string, SettingDefinition> by key, in the providers' order
     */
    public function all(): array
    {
        if (null !== $this->definitions) {
            return $this->definitions;
        }

        $definitions = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                if (isset($definitions[$definition->key])) {
                    throw new \LogicException(\sprintf('The setting "%s" is declared twice.', $definition->key));
                }
                foreach ($definition->levels as $level) {
                    if (!$this->levels->declares($level)) {
                        throw new \LogicException(\sprintf('The setting "%s" opens the level "%s", which the application does not declare (setting.levels).', $definition->key, $level));
                    }
                }
                $definitions[$definition->key] = $definition;
            }
        }

        return $this->definitions = $definitions;
    }
}
