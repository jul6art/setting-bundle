<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Translation;

use Jul6Art\SettingBundle\Scope\SettingContext;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The translator, with the context's reworded messages over the shipped ones: a key reworded for the request's
 * organisation — its app — is translated with that text, formatted with its parameters as a shipped message is; any
 * other key goes to the translator it decorates. The files are never rewritten.
 *
 * Without a context (a command, a worker without a request), the shipped messages only.
 */
final readonly class OverridingTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface, WarmableInterface
{
    public function __construct(
        private TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $translator,
        private TranslationOverrides $overrides,
        private MessageFormatterInterface $formatter,
        private ?TranslationContextProviderInterface $contexts = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[\Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $context = $this->contexts?->context();
        if ($context instanceof SettingContext) {
            $locale ??= $this->translator->getLocale();
            $message = $this->overrides->message($context, $domain ?? 'messages', $id, $locale);
            if (null !== $message) {
                return $this->formatter->format($message, $locale, $parameters);
            }
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    #[\Override]
    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * @return MessageCatalogueInterface[]
     */
    #[\Override]
    public function getCatalogues(): array
    {
        return $this->translator->getCatalogues();
    }

    #[\Override]
    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    #[\Override]
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * What the framework's translator answers, for whoever asks its decorator (`translation:extract`, the profiler).
     *
     * @return list<string>
     */
    public function getFallbackLocales(): array
    {
        if (!method_exists($this->translator, 'getFallbackLocales')) {
            return [];
        }
        $locales = $this->translator->getFallbackLocales();

        return \is_array($locales) ? array_values(array_filter($locales, \is_string(...))) : [];
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->translator instanceof WarmableInterface ? $this->translator->warmUp($cacheDir, $buildDir) : [];
    }
}
