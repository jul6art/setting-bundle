<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Scope;

/**
 * The levels the application declares (`setting.levels`), from the broadest to the most precise, after the global one.
 * The most precise value wins.
 */
final readonly class SettingLevels
{
    /**
     * @param list<string> $levels from the broadest to the most precise, the global one left out
     */
    public function __construct(
        private array $levels,
    ) {
    }

    public function declares(string $level): bool
    {
        return Scope::GLOBAL === $level || \in_array($level, $this->levels, true);
    }

    /**
     * @return list<string> the global level first, then the application's, broadest first
     */
    public function all(): array
    {
        return [Scope::GLOBAL, ...$this->levels];
    }

    /**
     * The scopes a context reads, broadest first, the global one always first.
     *
     * @return list<Scope>
     *
     * @throws \InvalidArgumentException when the context names a level the application does not declare
     */
    public function scopesOf(SettingContext $context): array
    {
        foreach (array_keys($context->subjects) as $level) {
            if (Scope::GLOBAL === $level || !\in_array($level, $this->levels, true)) {
                throw new \InvalidArgumentException(\sprintf('The level "%s" is not one of the application\'s (setting.levels).', $level));
            }
        }

        $scopes = [Scope::global()];
        foreach ($this->levels as $level) {
            if (isset($context->subjects[$level])) {
                $scopes[] = new Scope($level, $context->subjects[$level]);
            }
        }

        return $scopes;
    }
}
