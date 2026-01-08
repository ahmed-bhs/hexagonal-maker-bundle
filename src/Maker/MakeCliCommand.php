<?php

declare(strict_types=1);

/*
 * This file is part of the HexagonalMakerBundle package.
 *
 * (c) Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AhmedBhs\HexagonalMakerBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use AhmedBhs\HexagonalMakerBundle\Generator\HexagonalGenerator;

final class MakeCliCommand extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:cli-command';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new CLI Command (UI Layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The command name (e.g. <fg=yellow>CreateUser</>)')
            ->addArgument('command-name', InputArgument::OPTIONAL, 'The command name (e.g. <fg=yellow>app:user:create</>)')
            ->addOption('with-use-case', null, InputOption::VALUE_NONE, 'Also generate UseCase, Command and Input DTO')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new CLI Command');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withUseCase = $input->getOption('with-use-case');
        $commandName = $input->getArgument('command-name') ?? sprintf(
            'app:%s:%s',
            str_replace(['/', '\\'], ':', strtolower($path)),
            strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name))
        );

        // Generate CLI Command
        $this->generator->generateCliCommand($path, $name, $commandName);

        $generatedFiles = ['  - CLI Command: ' . $name . 'Command.php'];

        // Generate UseCase workflow if requested
        if ($withUseCase) {
            $io->text('Also generating UseCase workflow...');
            $this->generator->generateUseCase($path, $name);
            $this->generator->generateInput($path, $name . 'Input');
            $this->generator->generateCommand($path, $name, false);
            $generatedFiles[] = '  - UseCase: ' . $name . 'UseCase.php';
            $generatedFiles[] = '  - Command: ' . $name . 'Command.php (Application)';
            $generatedFiles[] = '  - Input: ' . $name . 'Input.php';

            $io->success('CLI Command with UseCase workflow generated successfully!');
            $io->text(['Generated files:'] + $generatedFiles);
        } else {
            $this->writeSuccessMessage($io);
            $io->text($generatedFiles);
        }

        $nextSteps = [
            'Next steps:',
            '  1. Add arguments and options in configure() method',
        ];

        if ($withUseCase) {
            $nextSteps[] = '  2. Implement UseCase logic in ' . $name . 'UseCase.php';
            $nextSteps[] = '  3. Call UseCase from CLI Command execute() method';
            $nextSteps[] = '  4. Run the command: bin/console ' . $commandName;
        } else {
            $nextSteps[] = '  2. Implement execute() method';
            $nextSteps[] = '  3. Run the command: bin/console ' . $commandName;
            $nextSteps[] = '  Tip: Use --with-use-case to auto-generate UseCase workflow';
        }

        $io->text($nextSteps);


        // Write all changes to disk
        $generator->writeChanges();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
