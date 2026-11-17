<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\SettingBundle\Doctrine\AbstractSettingValue;

/**
 * An application's entity, as the README shows it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'setting_value')]
#[ORM\UniqueConstraint(columns: ['setting_key', 'level', 'subject'])]
#[ORM\Index(columns: ['level', 'subject'])]
class SettingValue extends AbstractSettingValue
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
