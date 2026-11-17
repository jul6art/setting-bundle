<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Definition;

/**
 * What a setting holds. A duration is an integer with its unit ({@see SettingDefinition::$unit}): a bound is then a plain
 * number, and a form shows a plain number field.
 */
enum SettingType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case Text = 'text';
    /** One of {@see SettingDefinition::$choices}. */
    case Choice = 'choice';
    /** An ordered list of {@see SettingDefinition::$choices}, each at most once — a menu's entries, in their order. */
    case List = 'list';
}
