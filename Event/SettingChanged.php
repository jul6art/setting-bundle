<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Event;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Scope\Scope;

/**
 * A value set, changed or removed at a scope — dispatched once the store has it, and only when it changed. What a
 * journal, a real-time channel or a satellite's signal listens to.
 *
 * @phpstan-import-type SettingValue from SettingDefinition
 */
final readonly class SettingChanged
{
    /**
     * @param SettingValue|null $before the value set at this scope before; null when there was none
     * @param SettingValue|null $after  the value set at this scope now; null when it was removed
     */
    public function __construct(
        public string $key,
        public Scope $scope,
        public bool|int|string|array|null $before,
        public bool|int|string|array|null $after,
        public ?string $by,
    ) {
    }

    public function changed(): bool
    {
        return $this->before !== $this->after;
    }
}
