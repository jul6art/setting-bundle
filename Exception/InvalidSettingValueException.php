<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Exception;

/**
 * A value outside its setting's declaration, or written at a level the setting does not open.
 *
 * The reason is a short code a caller can turn into a message of its own — `type`, `min`, `max`, `choice`,
 * `duplicate`, `level` — and the limit, when there is one, the bound that was crossed.
 */
final class InvalidSettingValueException extends \InvalidArgumentException
{
    public const string TYPE = 'type';
    public const string MIN = 'min';
    public const string MAX = 'max';
    public const string CHOICE = 'choice';
    public const string DUPLICATE = 'duplicate';
    public const string LEVEL = 'level';

    public function __construct(
        public readonly string $key,
        public readonly string $reason,
        public readonly ?int $limit = null,
    ) {
        parent::__construct(\sprintf('The value of the setting "%s" is refused: %s%s.', $key, $reason, null === $limit ? '' : ' '.$limit));
    }
}
