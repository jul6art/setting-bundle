<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Tests\Unit;

use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingContext;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Tests\Fixtures\InMemoryTranslationOverrideStore;
use Jul6Art\SettingBundle\Tests\Fixtures\RecordingDispatcher;
use Jul6Art\SettingBundle\Translation\OverridingTranslator;
use Jul6Art\SettingBundle\Translation\TranslationContextProviderInterface;
use Jul6Art\SettingBundle\Translation\TranslationOverrideChanged;
use Jul6Art\SettingBundle\Translation\TranslationOverrides;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * The messages reworded for a context — the most precise level winning for a key —, cached by scope and language, and
 * the translator that reads them over the shipped files.
 */
#[CoversClass(TranslationOverrides::class)]
#[CoversClass(OverridingTranslator::class)]
final class TranslationOverridesTest extends TestCase
{
    private InMemoryTranslationOverrideStore $store;

    private ArrayAdapter $cache;

    private RecordingDispatcher $dispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryTranslationOverrideStore();
        $this->cache = new ArrayAdapter();
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testTheMostPreciseRewordingWinsKeyByKey(): void
    {
        $overrides = $this->overrides();
        $overrides->reword(Scope::global(), 'emails', 'fr', 'invitation.subject', 'Invitation');
        $overrides->reword(Scope::global(), 'emails', 'fr', 'invitation.greeting', 'Salut,');
        $overrides->reword(new Scope('organization', '1'), 'emails', 'fr', 'invitation.subject', 'Rejoignez %organization%');

        self::assertSame(['emails' => ['invitation.subject' => 'Rejoignez %organization%', 'invitation.greeting' => 'Salut,']], $this->overrides()->messages(SettingContext::of(['organization' => '1']), 'fr'));
        self::assertSame(['emails' => ['invitation.subject' => 'Invitation', 'invitation.greeting' => 'Salut,']], $this->overrides()->messages(SettingContext::of(['organization' => '2']), 'fr'));
        self::assertSame([], $this->overrides()->messages(SettingContext::of(['organization' => '1']), 'en'), 'Each language its own.');
    }

    public function testAWarmCacheReadsNoStoreAndARewordingDropsItsScope(): void
    {
        $context = SettingContext::of(['organization' => '1']);
        $this->overrides()->messages($context, 'fr');
        $this->overrides()->messages($context, 'fr');
        self::assertSame(1, $this->store->loads, 'One reading for both scopes, then the cache.');

        $this->overrides()->reword(new Scope('organization', '1'), 'messages', 'fr', 'nav.fleet', 'Flotte');
        self::assertSame('Flotte', $this->overrides()->message($context, 'messages', 'nav.fleet', 'fr'));
    }

    public function testAChangeIsDispatchedOnlyWhenTheTextChanges(): void
    {
        $overrides = $this->overrides();
        $scope = new Scope('organization', '1');
        $overrides->reword($scope, 'messages', 'fr', 'nav.fleet', 'Flotte', 'admin@example.org');
        $overrides->reword($scope, 'messages', 'fr', 'nav.fleet', 'Flotte');
        $overrides->restore($scope, 'messages', 'fr', 'nav.fleet');
        $overrides->restore($scope, 'messages', 'fr', 'nav.fleet');

        self::assertEquals([
            new TranslationOverrideChanged($scope, 'messages', 'fr', 'nav.fleet', null, 'Flotte', 'admin@example.org'),
            new TranslationOverrideChanged($scope, 'messages', 'fr', 'nav.fleet', 'Flotte', null, null),
        ], $this->dispatcher->events);
    }

    public function testTheTranslatorReadsTheRewordingOfItsContextFormattedAsAShippedMessage(): void
    {
        $shipped = new Translator('fr');
        $shipped->addLoader('array', new ArrayLoader());
        $shipped->addResource('array', ['invitation.subject' => '%inviter% vous invite — %organization%', 'invitation.greeting' => 'Bonjour,'], 'fr', 'emails');
        $this->overrides()->reword(new Scope('organization', '1'), 'emails', 'fr', 'invitation.subject', 'Rejoignez %organization% sur l’invitation de %inviter%');
        $contexts = new class implements TranslationContextProviderInterface {
            public ?SettingContext $current = null;

            #[\Override]
            public function context(): ?SettingContext
            {
                return $this->current;
            }
        };
        $translator = new OverridingTranslator($shipped, $this->overrides(), new MessageFormatter(), $contexts);
        $parameters = ['%inviter%' => 'Ada', '%organization%' => 'Acme'];

        self::assertSame('Ada vous invite — Acme', $translator->trans('invitation.subject', $parameters, 'emails'), 'Without a context, the shipped message.');
        $contexts->current = SettingContext::of(['organization' => '1']);
        self::assertSame('Rejoignez Acme sur l’invitation de Ada', $translator->trans('invitation.subject', $parameters, 'emails'));
        self::assertSame('Bonjour,', $translator->trans('invitation.greeting', [], 'emails'), 'A key not reworded: the shipped one.');
        $contexts->current = SettingContext::of(['organization' => '2']);
        self::assertSame('Ada vous invite — Acme', $translator->trans('invitation.subject', $parameters, 'emails'), 'Another organisation: the shipped one.');
    }

    private function overrides(): TranslationOverrides
    {
        return new TranslationOverrides(new SettingLevels(['organization', 'app']), $this->store, $this->cache, ['fr', 'en'], $this->dispatcher);
    }
}
