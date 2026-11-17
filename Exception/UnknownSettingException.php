<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Exception;

/**
 * A key no provider declares. A setting exists only once declared in code: there is no free key.
 */
final class UnknownSettingException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $key,
    ) {
        parent::__construct(\sprintf('No setting is declared under "%s".', $key));
    }
}
