<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Resolver;

use Jul6Art\SettingBundle\Definition\SettingDefinition;

/**
 * A setting as it applies in a context: its value, the level it comes from (null: the code's default), and the value
 * set at each level of the context — what a screen needs to say « set here » or « inherited », and what a reset gives.
 *
 * @phpstan-import-type SettingValue from SettingDefinition
 */
final readonly class ResolvedSetting
{
    /**
     * @param array<string, SettingValue> $values the value set at each level of the context, broadest first; a level
     *                                            with none is left out
     */
    public function __construct(
        public SettingDefinition $definition,
        public array $values,
    ) {
    }

    /**
     * @return SettingValue
     */
    public function value(): bool|int|string|array
    {
        return [] === $this->values ? $this->definition->default : array_last($this->values);
    }

    /**
     * The level the value comes from; null when it is the code's default.
     */
    public function source(): ?string
    {
        return array_key_last($this->values);
    }

    /**
     * The value set at this level, if any.
     *
     * @return SettingValue|null
     */
    public function at(string $level): bool|int|string|array|null
    {
        return $this->values[$level] ?? null;
    }

    /**
     * What would apply without the levels from this one down: what resetting the value at this level gives.
     */
    public function above(string $level): self
    {
        $values = [];
        foreach ($this->values as $at => $value) {
            if ($at === $level) {
                break;
            }
            $values[$at] = $value;
        }

        return new self($this->definition, $values);
    }
}
