<?php

declare(strict_types=1);

namespace Jul6Art\SettingBundle\Command;

use Jul6Art\SettingBundle\Exception\InvalidSettingValueException;
use Jul6Art\SettingBundle\Exception\UnknownSettingException;
use Jul6Art\SettingBundle\Scope\Scope;
use Jul6Art\SettingBundle\Writer\SettingWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sets a value from the command line — at the global level by default: the installation's own value.
 *
 *     bin/console setting:set invitation.lifetime_days 14
 *     bin/console setting:set menu.entries '["fleet","tasks"]' --level=organization --subject=42
 *     bin/console setting:set invitation.lifetime_days --reset
 *
 * The value is read as JSON (`true`, `14`, `["a","b"]`), and as a plain text when it is not JSON.
 */
#[AsCommand(name: 'setting:set', description: 'Sets a setting\'s value at a level, or removes it')]
final class SettingSetCommand extends Command
{
    public function __construct(
        private readonly SettingWriter $writer,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The setting\'s key')
            ->addArgument('value', InputArgument::OPTIONAL, 'The value, as JSON or as a plain text')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'The level', Scope::GLOBAL)
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'The subject at that level — an id', '')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Removes the value: the level above applies again');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = self::text($input->getArgument('key'));
        $scope = new Scope(self::text($input->getOption('level')), self::text($input->getOption('subject')));
        $value = $input->getArgument('value');

        try {
            if (true === $input->getOption('reset')) {
                $change = $this->writer->reset($key, $scope, 'setting:set');
            } elseif (\is_string($value)) {
                $change = $this->writer->set($key, $scope, self::decode($value), 'setting:set');
            } else {
                $io->error('A value, or --reset.');

                return Command::INVALID;
            }
        } catch (UnknownSettingException|InvalidSettingValueException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success($change->changed()
            ? \sprintf('%s at %s: %s.', $key, $scope->id(), null === $change->after ? 'removed' : json_encode($change->after, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE))
            : \sprintf('%s at %s: unchanged.', $key, $scope->id()));

        return Command::SUCCESS;
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function decode(string $value): mixed
    {
        try {
            return json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
