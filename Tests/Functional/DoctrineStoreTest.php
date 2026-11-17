<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\SettingBundle\Command\SettingSetCommand;
use Jul6Art\SettingBundle\Doctrine\DoctrineSettingStore;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Jul6Art\SettingBundle\Tests\Fixtures\Entity\SettingValue;
use Jul6Art\SettingBundle\Tests\Fixtures\TestSettings;
use Jul6Art\SettingBundle\Writer\SettingWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The values in an application's table, on a real container: the mapped superclass seen by Doctrine, one query for the
 * scopes of a context, a row per scope, and the command.
 */
#[CoversClass(DoctrineSettingStore::class)]
#[CoversClass(SettingSetCommand::class)]
final class DoctrineStoreTest extends AbstractFunctionalTestCase
{
    private ContainerInterface $container;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->boot('test', ['levels' => ['organization', 'app', 'user'], 'storage' => ['value_class' => SettingValue::class]], withOrm: true);
        $manager = $this->manager();
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
    }

    public function testAValueIsARowOfItsScope(): void
    {
        $this->writer()->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14, 'admin@example.org');
        $this->writer()->set(TestSettings::LIFETIME, new Scope('organization', '1'), 21, 'owner@example.org');

        $rows = $this->manager()->getRepository(SettingValue::class)->findAll();
        self::assertCount(1, $rows);
        self::assertSame([TestSettings::LIFETIME, 'organization', '1', 21, 'owner@example.org'], [$rows[0]->getSettingKey(), $rows[0]->getLevel(), $rows[0]->getSubject(), $rows[0]->getValue(), $rows[0]->getUpdatedBy()]);
    }

    public function testTheScopesOfAContextAreReadTogether(): void
    {
        $this->writer()->set(TestSettings::LIFETIME, Scope::global(), 10);
        $this->writer()->set(TestSettings::LIFETIME, new Scope('organization', '1'), 14);
        $this->writer()->set(TestSettings::FEATURE, new Scope('app', 'todo'), true);
        $this->writer()->set(TestSettings::LIFETIME, new Scope('organization', '2'), 3);

        $store = $this->container->get(SettingStoreInterface::class);
        self::assertInstanceOf(SettingStoreInterface::class, $store);
        // The keys in any order: the rows come in the database's.
        self::assertEquals(
            ['global:' => [TestSettings::LIFETIME => 10], 'organization:1' => [TestSettings::LIFETIME => 14], 'app:todo' => [TestSettings::FEATURE => true]],
            $store->load([Scope::global(), new Scope('organization', '1'), new Scope('app', 'todo')]),
        );

        $resolver = $this->container->get(SettingResolver::class);
        self::assertInstanceOf(SettingResolver::class, $resolver);
        $context = SettingContext::of(['organization' => '1', 'app' => 'todo']);
        self::assertSame(14, $resolver->get(TestSettings::LIFETIME, $context));
        self::assertTrue($resolver->get(TestSettings::FEATURE, $context));
    }

    public function testAResetRemovesTheRowAndAScopeGoesWithItsSubject(): void
    {
        $this->writer()->set(TestSettings::LIFETIME, new Scope('app', 'todo'), 14);
        $this->writer()->set(TestSettings::FEATURE, new Scope('app', 'todo'), true);
        $this->writer()->set(TestSettings::LIFETIME, new Scope('app', 'crm'), 3);

        $this->writer()->reset(TestSettings::LIFETIME, new Scope('app', 'todo'));
        self::assertCount(2, $this->manager()->getRepository(SettingValue::class)->findAll());

        $store = $this->container->get(SettingStoreInterface::class);
        self::assertInstanceOf(SettingStoreInterface::class, $store);
        $store->removeScope(new Scope('app', 'todo'));
        self::assertSame(['app:crm' => [TestSettings::LIFETIME => 3]], $store->load([new Scope('app', 'todo'), new Scope('app', 'crm')]));
    }

    public function testTheCommandSetsTheInstallationsValue(): void
    {
        $tester = $this->command();

        self::assertSame(Command::SUCCESS, $tester->execute(['key' => TestSettings::LIFETIME, 'value' => '14']));
        self::assertSame(Command::SUCCESS, $tester->execute(['key' => TestSettings::FEATURE, 'value' => 'true', '--level' => 'app', '--subject' => 'todo']));
        self::assertSame(Command::FAILURE, $tester->execute(['key' => TestSettings::LIFETIME, 'value' => '60']));
        self::assertStringContainsString('max 30', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['key' => 'nothing.here', 'value' => '1']));

        $resolver = $this->container->get(SettingResolver::class);
        self::assertInstanceOf(SettingResolver::class, $resolver);
        self::assertSame(14, $resolver->get(TestSettings::LIFETIME, SettingContext::global()));
        self::assertTrue($resolver->get(TestSettings::FEATURE, SettingContext::of(['app' => 'todo'])));

        self::assertSame(Command::SUCCESS, $tester->execute(['key' => TestSettings::LIFETIME, '--reset' => true]));
        self::assertSame(7, $resolver->get(TestSettings::LIFETIME, SettingContext::global()));
    }

    private function command(): CommandTester
    {
        $command = $this->container->get(SettingSetCommand::class);
        self::assertInstanceOf(SettingSetCommand::class, $command);

        return new CommandTester($command);
    }

    private function writer(): SettingWriter
    {
        $writer = $this->container->get(SettingWriter::class);
        self::assertInstanceOf(SettingWriter::class, $writer);

        return $writer;
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
