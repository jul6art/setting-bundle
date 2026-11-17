<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\SettingBundle\Scope\Scope;

/**
 * The reworded messages in the application's own table — the entity named by `setting.translations.override_class`.
 * Reading the scopes of a context in a language is one query. Writing flushes.
 */
final readonly class DoctrineTranslationOverrideStore implements TranslationOverrideStoreInterface
{
    /**
     * @param class-string<AbstractTranslationOverride> $overrideClass
     */
    public function __construct(
        private ManagerRegistry $doctrine,
        private string $overrideClass,
    ) {
    }

    #[\Override]
    public function load(array $scopes, string $locale): array
    {
        if ([] === $scopes) {
            return [];
        }

        $query = $this->manager()->createQueryBuilder()
            ->select('o.domain AS domain', 'o.translationKey AS translationKey', 'o.level AS level', 'o.subject AS subject', 'o.message AS message')
            ->from($this->overrideClass, 'o')
            ->andWhere('o.locale = :locale')
            ->setParameter('locale', $locale);
        $any = $query->expr()->orX();
        foreach ($scopes as $index => $scope) {
            $any->add(\sprintf('(o.level = :level%1$d AND o.subject = :subject%1$d)', $index));
            $query->setParameter('level'.$index, $scope->level)->setParameter('subject'.$index, $scope->subject);
        }

        /** @var list<array{domain: string, translationKey: string, level: string, subject: string, message: string}> $rows */
        $rows = $query->andWhere($any)->getQuery()->getArrayResult();
        $found = [];
        foreach ($rows as $row) {
            $found[new Scope($row['level'], $row['subject'])->id()][$row['domain']][$row['translationKey']] = $row['message'];
        }

        return $found;
    }

    #[\Override]
    public function save(Scope $scope, string $domain, string $locale, string $key, string $message, ?string $by): void
    {
        $manager = $this->manager();
        $row = $this->find($scope, $domain, $locale, $key);
        if (!$row instanceof AbstractTranslationOverride) {
            $row = new ($this->overrideClass)($domain, $locale, $key, $scope->level, $scope->subject);
            $manager->persist($row);
        }
        $row->reword($message, $by);
        $manager->flush();
    }

    #[\Override]
    public function remove(Scope $scope, string $domain, string $locale, string $key): void
    {
        $manager = $this->manager();
        $row = $this->find($scope, $domain, $locale, $key);
        if ($row instanceof AbstractTranslationOverride) {
            $manager->remove($row);
        }
        $manager->flush();
    }

    private function find(Scope $scope, string $domain, string $locale, string $key): ?AbstractTranslationOverride
    {
        return $this->manager()->getRepository($this->overrideClass)->findOneBy(['domain' => $domain, 'locale' => $locale, 'translationKey' => $key, 'level' => $scope->level, 'subject' => $scope->subject]);
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass($this->overrideClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine entity manager maps "%s" (setting.translations.override_class).', $this->overrideClass));
        }

        return $manager;
    }
}
