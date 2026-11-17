<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Definition;

use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use Jul6Art\SettingBundle\Scope\Scope;

/**
 * A setting, as code declares it: its key, what it holds, its default, its bounds, and the levels where it can be
 * overridden. A value outside the declaration is refused; a value stored before the declaration narrowed is ignored
 * at reading, so the next level up applies.
 *
 * The global level is always open: it is the installation's own value, set by its configuration or by
 * `setting:set`. `$levels` names the other ones, among those the application declares (`setting.levels`).
 *
 * @phpstan-type SettingValue bool|int|string|list<string>
 */
final readonly class SettingDefinition
{
    private const string KEY = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    /**
     * @param SettingValue $default
     * @param list<string> $levels  the levels where it can be overridden, besides the global one
     * @param ?int         $min     for an integer, its lowest value; for a text, its shortest length
     * @param ?int         $max     for an integer, its highest value; for a text, its longest length
     * @param list<string> $choices what a choice or a list picks from
     * @param ?string      $unit    what an integer counts — `days`, `minutes` — for whoever shows it
     * @param ?string      $label   a translation key, for whoever shows it
     * @param ?string      $group   where it is shown with others
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public bool|int|string|array $default,
        public array $levels = [],
        public ?int $min = null,
        public ?int $max = null,
        public array $choices = [],
        public ?string $unit = null,
        public ?string $label = null,
        public ?string $group = null,
    ) {
        if (1 !== preg_match(self::KEY, $key)) {
            throw new \InvalidArgumentException(\sprintf('A setting\'s key is dotted lowercase words, "%s" is not.', $key));
        }
        if (\in_array($type, [SettingType::Choice, SettingType::List], true) && [] === $choices) {
            throw new \LogicException(\sprintf('The setting "%s" picks from choices, and declares none.', $key));
        }

        try {
            $normalized = $this->normalize($default);
        } catch (InvalidSettingValueException $exception) {
            throw new \LogicException(\sprintf('The default of the setting "%s" is outside its own declaration (%s).', $key, $exception->reason), 0, $exception);
        }
        if ($normalized !== $default) {
            throw new \LogicException(\sprintf('The default of the setting "%s" is not in its normal form.', $key));
        }
    }

    /**
     * Whether a value can be set at this level. The global level always can.
     */
    public function opens(string $level): bool
    {
        return Scope::GLOBAL === $level || \in_array($level, $this->levels, true);
    }

    /**
     * The value, checked against the declaration — or refused, with the reason.
     *
     * @return SettingValue
     *
     * @throws InvalidSettingValueException
     */
    public function normalize(mixed $value): bool|int|string|array
    {
        return match ($this->type) {
            SettingType::Bool => \is_bool($value) ? $value : throw new InvalidSettingValueException($this->key, InvalidSettingValueException::TYPE),
            SettingType::Int => $this->integer($value),
            SettingType::Text => $this->text($value),
            SettingType::Choice => \is_string($value) && \in_array($value, $this->choices, true) ? $value : throw new InvalidSettingValueException($this->key, \is_string($value) ? InvalidSettingValueException::CHOICE : InvalidSettingValueException::TYPE),
            SettingType::List => $this->list($value),
        };
    }

    private function integer(mixed $value): int
    {
        if (!\is_int($value)) {
            throw new InvalidSettingValueException($this->key, InvalidSettingValueException::TYPE);
        }
        $this->bound($value);

        return $value;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new InvalidSettingValueException($this->key, InvalidSettingValueException::TYPE);
        }
        $this->bound(mb_strlen($value));

        return $value;
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidSettingValueException($this->key, InvalidSettingValueException::TYPE);
        }
        $items = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new InvalidSettingValueException($this->key, InvalidSettingValueException::TYPE);
            }
            if (!\in_array($item, $this->choices, true)) {
                throw new InvalidSettingValueException($this->key, InvalidSettingValueException::CHOICE);
            }
            if (\in_array($item, $items, true)) {
                throw new InvalidSettingValueException($this->key, InvalidSettingValueException::DUPLICATE);
            }
            $items[] = $item;
        }

        return $items;
    }

    private function bound(int $measure): void
    {
        if (null !== $this->min && $measure < $this->min) {
            throw new InvalidSettingValueException($this->key, InvalidSettingValueException::MIN, $this->min);
        }
        if (null !== $this->max && $measure > $this->max) {
            throw new InvalidSettingValueException($this->key, InvalidSettingValueException::MAX, $this->max);
        }
    }
}
