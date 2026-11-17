<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Feature;

use Jul6Art\AclBundle\Contract\AclUserInterface;
use Jul6Art\AclBundle\Contract\FeatureCheckerInterface;
use Jul6Art\SettingBundle\Scope\SettingContext;

/**
 * The other half of acl-bundle's `#[RequiresFeature]`, when the flags are settings: the user's tenant is the subject of
 * the level `setting.features.tenant_level` — its organisation, usually —, and the flag is read in that context.
 *
 * Registered only when acl-bundle is installed and the level is configured: an application that checks its features
 * another way keeps its own checker.
 */
final readonly class SettingFeatureChecker implements FeatureCheckerInterface
{
    public function __construct(
        private Features $features,
        private string $tenantLevel,
    ) {
    }

    #[\Override]
    public function isEnabled(AclUserInterface $user, string $featureCode): bool
    {
        $tenant = $user->getTenant()?->getId();
        if (null === $tenant) {
            return false;
        }

        return $this->features->isEnabled($featureCode, SettingContext::of([$this->tenantLevel => (string) $tenant]));
    }
}
