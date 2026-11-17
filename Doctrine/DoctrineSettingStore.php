<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;

/**
 * The values in the application's own table — the entity named by `setting.storage.value_class`.
 *
 * Reading the scopes of a context is one query, whatever their number. Writing flushes: whatever the caller persisted
 * before — a journal's line — goes in the same transaction.
 */
final readonly class DoctrineSettingStore implements SettingStoreInterface
{
    /**
     * @param class-string<AbstractSettingValue> $valueClass
     */
    public function __construct(
        private ManagerRegistry $doctrine,
        private string $valueClass,
    ) {
    }

    #[\Override]
    public function load(array $scopes): array
    {
        if ([] === $scopes) {
            return [];
        }

        $query = $this->manager()->createQueryBuilder()
            ->select('v.settingKey AS settingKey', 'v.level AS level', 'v.subject AS subject', 'v.value AS value')
            ->from($this->valueClass, 'v');
        $any = $query->expr()->orX();
        foreach ($scopes as $index => $scope) {
            $any->add(\sprintf('(v.level = :level%1$d AND v.subject = :subject%1$d)', $index));
            $query->setParameter('level'.$index, $scope->level)->setParameter('subject'.$index, $scope->subject);
        }

        /** @var list<array{settingKey: string, level: string, subject: string, value: mixed}> $rows */
        $rows = $query->where($any)->getQuery()->getArrayResult();
        $found = [];
        foreach ($rows as $row) {
            $found[new Scope($row['level'], $row['subject'])->id()][$row['settingKey']] = $row['value'];
        }

        return $found;
    }

    #[\Override]
    public function save(string $key, Scope $scope, mixed $value, ?string $by): void
    {
        $manager = $this->manager();
        $row = $this->find($key, $scope);
        if (!$row instanceof AbstractSettingValue) {
            $row = new ($this->valueClass)($key, $scope->level, $scope->subject);
            $manager->persist($row);
        }
        $row->change($value, $by);
        $manager->flush();
    }

    #[\Override]
    public function remove(string $key, Scope $scope): void
    {
        $manager = $this->manager();
        $row = $this->find($key, $scope);
        if ($row instanceof AbstractSettingValue) {
            $manager->remove($row);
        }
        $manager->flush();
    }

    /**
     * ⚠️ A bulk DQL delete: it bypasses the unit of work — no lifecycle event, no listener, no audit sees these rows go.
     * Whoever deletes the subject records that.
     */
    #[\Override]
    public function removeScope(Scope $scope): void
    {
        $this->manager()->createQueryBuilder()
            ->delete($this->valueClass, 'v')
            ->where('v.level = :level AND v.subject = :subject')
            ->setParameter('level', $scope->level)
            ->setParameter('subject', $scope->subject)
            ->getQuery()
            ->execute();
    }

    private function find(string $key, Scope $scope): ?AbstractSettingValue
    {
        return $this->manager()->getRepository($this->valueClass)->findOneBy(['settingKey' => $key, 'level' => $scope->level, 'subject' => $scope->subject]);
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass($this->valueClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine entity manager maps "%s" (setting.storage.value_class).', $this->valueClass));
        }

        return $manager;
    }
}
