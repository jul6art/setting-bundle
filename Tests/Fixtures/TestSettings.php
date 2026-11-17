<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures;

use Jul6Art\SettingBundle\Definition\SettingDefinition;
use Jul6Art\SettingBundle\Definition\SettingDefinitionProviderInterface;
use Jul6Art\SettingBundle\Definition\SettingType;

/**
 * Two settings: a lifetime an organisation or an app overrides, a switch an app or an account does.
 */
final class TestSettings implements SettingDefinitionProviderInterface
{
    public const string LIFETIME = 'invitation.lifetime_days';

    public const string FEATURE = 'reports.enabled';

    #[\Override]
    public function definitions(): iterable
    {
        yield new SettingDefinition(self::LIFETIME, SettingType::Int, 7, ['organization', 'app'], min: 1, max: 30, unit: 'days');
        yield new SettingDefinition(self::FEATURE, SettingType::Bool, false, ['app', 'user']);
    }
}
