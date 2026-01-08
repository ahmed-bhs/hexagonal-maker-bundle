<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Maker;

use AhmedBhs\HexagonalMakerBundle\Config\DoctrineConfigUpdater;
use AhmedBhs\HexagonalMakerBundle\Config\MessengerConfigUpdater;
use AhmedBhs\HexagonalMakerBundle\Config\ServicesConfigUpdater;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Initialize hexagonal architecture configuration in a Symfony project.
 *
 * This command sets up all necessary configuration files for hexagonal architecture:
 * - Messenger (CQRS buses)
 * - Services (Domain exclusions)
 */
final class MakeInitCommand extends AbstractMaker
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:init';
    }

    public static function getCommandDescription(): string
    {
        return 'Initialize hexagonal architecture configuration (one-time setup)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command->setHelp(<<<'HELP'
The <info>%command.name%</info> command initializes hexagonal architecture configuration:

  <info>php %command.full_name%</info>

This is a one-time setup that configures:
  • Messenger buses (command.bus, query.bus) for CQRS
  • Services exclusions for Domain layer (entities, value objects)

Run this command once at the beginning of your hexagonal project.
HELP
            );
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // No dependencies required
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('🏗️ Hexagonal Architecture - Initial Configuration');
        $io->writeln('Setting up configuration files for hexagonal architecture...');
        $io->newLine();

        $configured = [];
        $skipped = [];
        $errors = [];

        // 1. Configure Messenger (CQRS buses)
        $io->section('📨 Configuring Messenger (CQRS)');
        try {
            $messengerUpdater = new MessengerConfigUpdater($this->projectDir);

            if ($messengerUpdater->add()) {
                $configured[] = '✓ Messenger buses configured (command.bus, query.bus)';
                $io->writeln('  <fg=green>✓</> command.bus - for Commands (with validation + transaction)');
                $io->writeln('  <fg=green>✓</> query.bus - for Queries (with validation only)');
            } else {
                $skipped[] = 'ℹ Messenger buses already configured';
                $io->writeln('  <fg=yellow>ℹ</> Buses already configured, skipping');
            }
        } catch (\Exception $e) {
            $errors[] = '✗ Messenger configuration failed: ' . $e->getMessage();
            $io->error('Failed to configure Messenger: ' . $e->getMessage());
        }

        // 2. Configure Services (Domain exclusions)
        $io->section('🔧 Configuring Services');
        try {
            $servicesUpdater = new ServicesConfigUpdater($this->projectDir);

            if ($servicesUpdater->addDomainExclusions()) {
                $configured[] = '✓ Domain exclusions configured';
                $io->writeln('  <fg=green>✓</> Domain/Model excluded from autowiring');
                $io->writeln('  <fg=green>✓</> Domain/ValueObject excluded from autowiring');
            } else {
                $skipped[] = 'ℹ Domain exclusions already configured';
                $io->writeln('  <fg=yellow>ℹ</> Exclusions already configured, skipping');
            }
        } catch (\Exception $e) {
            $errors[] = '✗ Services configuration failed: ' . $e->getMessage();
            $io->error('Failed to configure Services: ' . $e->getMessage());
        }

        // Summary
        $io->newLine();
        $io->section('📋 Summary');

        if (!empty($configured)) {
            $io->writeln('<fg=green>Configured:</>');
            foreach ($configured as $item) {
                $io->writeln('  ' . $item);
            }
        }

        if (!empty($skipped)) {
            $io->newLine();
            $io->writeln('<fg=yellow>Skipped (already configured):</>');
            foreach ($skipped as $item) {
                $io->writeln('  ' . $item);
            }
        }

        if (!empty($errors)) {
            $io->newLine();
            $io->writeln('<fg=red>Errors:</>');
            foreach ($errors as $item) {
                $io->writeln('  ' . $item);
            }
        }

        $io->newLine();

        if (empty($errors)) {
            $io->success('Hexagonal architecture initialized successfully!');

            $io->writeln([
                'Next steps:',
                '  1. Start creating your modules:',
                '     <info>php bin/console make:hexagonal:entity module/path EntityName --properties="..."</info>',
                '',
                '  2. The bundle will auto-configure everything for you!',
                '',
                '  3. Verify configuration anytime with:',
                '     <info>php bin/console make:hexagonal:doctor</info>',
            ]);
        } else {
            $io->warning('Some configuration steps failed. Please check the errors above.');
        }

        // Write changes (empty in this case, but required by interface)
        $generator->writeChanges();
    }
}
