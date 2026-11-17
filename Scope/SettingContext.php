<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Scope;

/**
 * What a reading is made for: one subject per level — the organisation of the request, its app, its account. A level
 * left out is skipped; the global level is always read.
 *
 * ```php
 * $resolver->get('invitation.lifetime_days', SettingContext::of(['organization' => (string) $organization->getId()]));
 * ```
 */
final readonly class SettingContext
{
    /**
     * @param array<string, string> $subjects the subject of each level, by level
     */
    private function __construct(
        public array $subjects,
    ) {
    }

    /**
     * @param array<string, string> $subjects the subject of each level, by level
     */
    public static function of(array $subjects): self
    {
        return new self($subjects);
    }

    /**
     * The global level alone: the installation's value, or the code's default.
     */
    public static function global(): self
    {
        return new self([]);
    }

    /**
     * The same context, with one more level — or another subject at that level.
     */
    public function with(string $level, string $subject): self
    {
        return new self([...$this->subjects, $level => $subject]);
    }
}
