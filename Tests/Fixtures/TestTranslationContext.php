<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures;

use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Translation\TranslationContextProviderInterface;

/**
 * The context a test sets: an organisation's, or none.
 */
final class TestTranslationContext implements TranslationContextProviderInterface
{
    public ?SettingContext $current = null;

    #[\Override]
    public function context(): ?SettingContext
    {
        return $this->current;
    }
}
