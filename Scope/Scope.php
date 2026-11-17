<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Scope;

/**
 * Where a value is set: a level, and the subject it is set for at that level — an organisation's id at the level
 * `organization`, an app's at `app`. The global level has one subject, the empty string.
 */
final readonly class Scope
{
    public const string GLOBAL = 'global';

    public function __construct(
        public string $level,
        public string $subject,
    ) {
    }

    public static function global(): self
    {
        return new self(self::GLOBAL, '');
    }

    /**
     * One string per scope, for a cache key or an array index.
     */
    public function id(): string
    {
        return $this->level.':'.$this->subject;
    }
}
