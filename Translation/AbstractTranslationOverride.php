<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A message reworded at a level, for a subject: the text that replaces the shipped one for a key, a domain and a
 * language — the files are never rewritten.
 *
 * The application's entity extends this and declares its table, its id, its unique constraint on (`domain`, `locale`,
 * `translation_key`, `level`, `subject`) and its index on (`level`, `subject`, `locale`), what a reading names. It keeps
 * this constructor: the store creates rows through it.
 */
#[ORM\MappedSuperclass]
abstract class AbstractTranslationOverride
{
    #[ORM\Column(type: Types::TEXT)]
    protected string $message = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    /** Who reworded it — an address, a command — for whoever reads the row. */
    #[ORM\Column(length: 180, nullable: true)]
    protected ?string $updatedBy = null;

    public function __construct(
        #[ORM\Column(length: 60)]
        protected string $domain,
        #[ORM\Column(length: 10)]
        protected string $locale,
        #[ORM\Column(name: 'translation_key', length: 190)]
        protected string $translationKey,
        #[ORM\Column(length: 40)]
        protected string $level,
        #[ORM\Column(length: 64)]
        protected string $subject,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function reword(string $message, ?string $by): void
    {
        $this->message = $message;
        $this->updatedBy = $by;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
