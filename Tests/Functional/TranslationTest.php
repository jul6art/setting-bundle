<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Tests\Fixtures\Entity\SettingValue;
use Jul6Art\SettingBundle\Tests\Fixtures\Entity\TranslationOverride;
use Jul6Art\SettingBundle\Tests\Fixtures\TestTranslationContext;
use Jul6Art\SettingBundle\Translation\DoctrineTranslationOverrideStore;
use Jul6Art\SettingBundle\Translation\OverridingTranslator;
use Jul6Art\SettingBundle\Translation\TranslationOverrides;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The reworded messages on a real container: the translator decorated, the rows in the application's table, the
 * context the application provides.
 */
#[CoversClass(DoctrineTranslationOverrideStore::class)]
final class TranslationTest extends AbstractFunctionalTestCase
{
    public function testTheTranslatorReadsTheRewordingOfTheContextFromTheTable(): void
    {
        $container = $this->boot('test', ['levels' => ['organization'], 'storage' => ['value_class' => SettingValue::class], 'translations' => ['override_class' => TranslationOverride::class, 'locales' => ['fr', 'en']]], withOrm: true);
        $manager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $translator = $container->get('translator');
        self::assertInstanceOf(OverridingTranslator::class, $translator);
        $overrides = $container->get(TranslationOverrides::class);
        self::assertInstanceOf(TranslationOverrides::class, $overrides);
        $contexts = $container->get(TestTranslationContext::class);
        self::assertInstanceOf(TestTranslationContext::class, $contexts);

        $overrides->reword(new Scope('organization', '1'), 'messages', 'fr', 'greeting', 'Salut %name%', 'admin@example.org');
        self::assertCount(1, $manager->getRepository(TranslationOverride::class)->findAll());

        self::assertSame('greeting', $translator->trans('greeting', ['%name%' => 'Ada'], null, 'fr'), 'No context: the shipped key.');
        $contexts->current = SettingContext::of(['organization' => '1']);
        self::assertSame('Salut Ada', $translator->trans('greeting', ['%name%' => 'Ada'], null, 'fr'));

        $overrides->restore(new Scope('organization', '1'), 'messages', 'fr', 'greeting');
        self::assertSame([], $manager->getRepository(TranslationOverride::class)->findAll());
        self::assertSame('greeting', $translator->trans('greeting', ['%name%' => 'Ada'], null, 'fr'));
    }

    public function testWithoutAnOverrideClassTheTranslatorIsTheFrameworks(): void
    {
        $container = $this->boot('test', ['levels' => ['organization'], 'storage' => ['value_class' => SettingValue::class]], withOrm: true);

        self::assertNotInstanceOf(OverridingTranslator::class, $container->get('translator'));
    }
}
