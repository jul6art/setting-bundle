<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Util\Clock;

/**
 * A value set for a setting, at a level, for a subject: one row each.
 *
 * The application's entity extends this and declares what a base class cannot: its table, its id, its unique
 * constraint on (`setting_key`, `level`, `subject`), its index on (`level`, `subject`) — the scopes of a context are
 * read by them, and the unique constraint starts with the key —, its audit attribute. It keeps this constructor: the
 * store creates rows through it.
 *
 * ```php
 * #[ORM\Entity]
 * #[ORM\Table(name: 'setting_value')]
 * #[ORM\UniqueConstraint(columns: ['setting_key', 'level', 'subject'])]
 * #[ORM\Index(columns: ['level', 'subject'])]
 * class SettingValue extends AbstractSettingValue
 * {
 *     #[ORM\Id, ORM\GeneratedValue, ORM\Column]
 *     private ?int $id = null;
 * }
 * ```
 */
#[ORM\MappedSuperclass]
abstract class AbstractSettingValue
{
    /** As written, JSON-encoded: a boolean, an integer, a text or a list of texts. */
    #[ORM\Column(type: Types::JSON)]
    protected mixed $value = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    /** Who set it — an address, a command — for whoever reads the row; never a secret. */
    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $updatedBy = null;

    public function __construct(
        #[ORM\Column(name: 'setting_key', length: 120)]
        protected string $settingKey,
        #[ORM\Column(length: 40)]
        protected string $level,
        /** The organisation's, the app's or the account's id at its level; the empty string at the global one. */
        #[ORM\Column(length: 64)]
        protected string $subject,
    ) {
        $this->updatedAt = Clock::now();
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function change(mixed $value, ?string $by): void
    {
        $this->value = $value;
        $this->updatedBy = $by;
        $this->updatedAt = Clock::now();
    }
}
