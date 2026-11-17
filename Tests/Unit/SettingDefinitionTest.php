<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Unit;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingType;
use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingDefinition::class)]
final class SettingDefinitionTest extends TestCase
{
    public function testAnIntegerKeepsWithinItsBounds(): void
    {
        $definition = new SettingDefinition('invitation.lifetime_days', SettingType::Int, 7, ['organization'], min: 1, max: 30, unit: 'days');

        self::assertSame(14, $definition->normalize(14));
        self::assertSame(InvalidSettingValueException::MIN, $this->refusal($definition, 0)->reason);
        self::assertSame(30, $this->refusal($definition, 31)->limit);
        self::assertSame(InvalidSettingValueException::TYPE, $this->refusal($definition, '14')->reason);
    }

    /**
     * @return iterable<string, array{SettingDefinition, mixed, string}>
     */
    public static function refusals(): iterable
    {
        $bool = new SettingDefinition('feature.reports', SettingType::Bool, false);
        $text = new SettingDefinition('mail.signature', SettingType::Text, 'Bye', max: 5);
        $choice = new SettingDefinition('ui.density', SettingType::Choice, 'comfortable', choices: ['comfortable', 'compact']);
        $list = new SettingDefinition('menu.entries', SettingType::List, ['fleet', 'tasks'], choices: ['fleet', 'tasks', 'journal']);

        yield 'a boolean as a number' => [$bool, 1, InvalidSettingValueException::TYPE];
        yield 'a text too long' => [$text, 'Goodbye', InvalidSettingValueException::MAX];
        yield 'a choice unknown' => [$choice, 'airy', InvalidSettingValueException::CHOICE];
        yield 'a list with an entry unknown' => [$list, ['fleet', 'costs'], InvalidSettingValueException::CHOICE];
        yield 'a list with an entry twice' => [$list, ['fleet', 'fleet'], InvalidSettingValueException::DUPLICATE];
        yield 'a list as a map' => [$list, ['a' => 'fleet'], InvalidSettingValueException::TYPE];
    }

    #[DataProvider('refusals')]
    public function testAValueOutsideItsDeclarationIsRefused(SettingDefinition $definition, mixed $value, string $reason): void
    {
        self::assertSame($reason, $this->refusal($definition, $value)->reason);
    }

    public function testAListKeepsItsOrder(): void
    {
        $definition = new SettingDefinition('menu.entries', SettingType::List, ['fleet', 'tasks'], choices: ['fleet', 'tasks', 'journal']);

        self::assertSame(['journal', 'fleet'], $definition->normalize(['journal', 'fleet']));
    }

    public function testTheGlobalLevelIsAlwaysOpen(): void
    {
        $definition = new SettingDefinition('invitation.lifetime_days', SettingType::Int, 7, ['organization']);

        self::assertTrue($definition->opens('global'));
        self::assertTrue($definition->opens('organization'));
        self::assertFalse($definition->opens('user'));
    }

    public function testADefaultOutsideItsDeclarationIsAMistakeOfCode(): void
    {
        $this->expectException(\LogicException::class);

        new SettingDefinition('invitation.lifetime_days', SettingType::Int, 60, min: 1, max: 30);
    }

    public function testAChoiceWithoutChoicesIsAMistakeOfCode(): void
    {
        $this->expectException(\LogicException::class);

        new SettingDefinition('ui.density', SettingType::Choice, 'compact');
    }

    public function testAKeyIsDottedLowercaseWords(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingDefinition('InvitationLifetime', SettingType::Int, 7);
    }

    private function refusal(SettingDefinition $definition, mixed $value): InvalidSettingValueException
    {
        try {
            $definition->normalize($value);
        } catch (InvalidSettingValueException $exception) {
            return $exception;
        }

        self::fail('The value was accepted.');
    }
}
