<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\SettingBundle\Translation\AbstractTranslationOverride;

/**
 * An application's entity for the reworded messages, as the README shows it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'setting_translation')]
#[ORM\UniqueConstraint(columns: ['domain', 'locale', 'translation_key', 'level', 'subject'])]
#[ORM\Index(columns: ['level', 'subject', 'locale'])]
class TranslationOverride extends AbstractTranslationOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
