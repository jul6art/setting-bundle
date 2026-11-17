<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Jul6Art\SettingBundle\Scope\SettingContext;

/**
 * Whose rewording applies to what is translated now — the request's organisation, its app —; null when nobody's:
 * a command, a worker without a request. The application registers one; without it, the shipped messages only.
 */
interface TranslationContextProviderInterface
{
    public function context(): ?SettingContext;
}
