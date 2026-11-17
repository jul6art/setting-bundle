<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Writer;

use Jul6Art\SettingBundle\Definition\SettingRegistry;
use Jul6Art\SettingBundle\Event\SettingChanged;
use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use Jul6Art\SettingBundle\Exception\UnknownSettingException;
use Jul6Art\SettingBundle\Resolver\SettingResolver;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Scope\SettingLevels;
use Jul6Art\SettingBundle\Store\SettingStoreInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Sets a value at a scope, or removes it so the level above applies again: checked against its declaration, stored,
 * its scope's cache dropped, and {@see SettingChanged} dispatched when something changed.
 *
 * Who may write — a permission, a validation, a journal — is the application's to decide before calling it.
 */
final readonly class SettingWriter
{
    public function __construct(
        private SettingRegistry $registry,
        private SettingLevels $levels,
        private SettingStoreInterface $store,
        private SettingResolver $resolver,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * @throws UnknownSettingException
     * @throws InvalidSettingValueException when the value is outside the declaration, or the level not open
     */
    public function set(string $key, Scope $scope, mixed $value, ?string $by = null): SettingChanged
    {
        $definition = $this->registry->get($key);
        $this->open($key, $scope);
        $after = $definition->normalize($value);
        $change = new SettingChanged($key, $scope, $this->current($key, $scope), $after, $by);
        if ($change->changed()) {
            $this->store->save($key, $scope, $after, $by);
            $this->done($change);
        }

        return $change;
    }

    /**
     * Removes the value set at this scope: the level above applies again.
     *
     * @throws UnknownSettingException
     * @throws InvalidSettingValueException when the level is not open
     */
    public function reset(string $key, Scope $scope, ?string $by = null): SettingChanged
    {
        $this->registry->get($key);
        $this->open($key, $scope);
        $change = new SettingChanged($key, $scope, $this->current($key, $scope), null, $by);
        if ($change->changed()) {
            $this->store->remove($key, $scope);
            $this->done($change);
        }

        return $change;
    }

    private function open(string $key, Scope $scope): void
    {
        if (!$this->levels->declares($scope->level) || !$this->registry->get($key)->opens($scope->level)) {
            throw new InvalidSettingValueException($key, InvalidSettingValueException::LEVEL);
        }
    }

    /**
     * @return bool|int|string|list<string>|null
     */
    private function current(string $key, Scope $scope): bool|int|string|array|null
    {
        $definition = $this->registry->get($key);
        $stored = $this->store->load([$scope])[$scope->id()] ?? [];
        if (!\array_key_exists($key, $stored)) {
            return null;
        }

        try {
            return $definition->normalize($stored[$key]);
        } catch (InvalidSettingValueException) {
            return null;
        }
    }

    private function done(SettingChanged $change): void
    {
        $this->resolver->forget($change->scope);
        $this->dispatcher?->dispatch($change);
    }
}
