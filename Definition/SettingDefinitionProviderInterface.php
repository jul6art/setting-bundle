<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Definition;

/**
 * Declares settings. Every service implementing it is collected (the tag `setting.definition_provider`, autoconfigured);
 * a module declares its own, next to the code that reads them.
 *
 * ```php
 * final class InvitationSettings implements SettingDefinitionProviderInterface
 * {
 *     public const string LIFETIME = 'invitation.lifetime_days';
 *
 *     public function definitions(): iterable
 *     {
 *         yield new SettingDefinition(self::LIFETIME, SettingType::Int, 7, ['organization'], min: 1, max: 30, unit: 'days');
 *     }
 * }
 * ```
 */
interface SettingDefinitionProviderInterface
{
    /**
     * @return iterable<SettingDefinition>
     */
    public function definitions(): iterable;
}
