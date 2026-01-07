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

final class MakeController extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:controller';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Web Controller (UI Layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g. <fg=yellow>CreateUser</>)')
            ->addArgument('route', InputArgument::OPTIONAL, 'The route path (e.g. <fg=yellow>/users/create</>)')
            ->addOption('with-workflow', null, InputOption::VALUE_NONE, 'Also generate Form, UseCase, Command, and Input DTO')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Web Controller');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $route = $input->getArgument('route') ?? '/'.strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
        $withWorkflow = $input->getOption('with-workflow');

        // Extract entity name
        $entityName = preg_replace('/^(Create|Update|Delete|Show|List|Search)/', '', $name);

        // Generate Controller
        $this->generator->generateController($path, $name, $route);

        // Generate complete workflow if requested
        if ($withWorkflow) {
            $io->text('Generating complete workflow...');

            // Generate Form Type
            $this->generator->generateForm($path, $entityName);

            // Generate Use Case
            $this->generator->generateUseCase($path, $name);

            // Generate Input DTO
            $this->generator->generateInput($path, $name . 'Input');

            // Generate Command
            $this->generator->generateCommand($path, $name, false);

            $io->success('Controller with complete workflow generated successfully!');
            $io->text([
                'Generated files:',
                '  - Controller: ' . $name . 'Controller.php',
                '  - Form: ' . $entityName . 'Type.php',
                '  - UseCase: ' . $name . 'UseCase.php',
                '  - Input DTO: ' . $name . 'Input.php',
                '  - Command: ' . $name . 'Command.php',
                '  - Command Handler: ' . $name . 'CommandHandler.php',
            ]);
        } else {
            $this->writeSuccessMessage($io);
        }

        $nextSteps = ['Next steps:'];

        if ($withWorkflow) {
            $nextSteps[] = '  1. Map form fields in ' . $entityName . 'Type.php';
            $nextSteps[] = '  2. Add validation in ' . $name . 'Input.php';
            $nextSteps[] = '  3. Implement business logic in ' . $name . 'UseCase.php';
            $nextSteps[] = '  4. Create Twig template for the view';
        } else {
            $nextSteps[] = '  1. Implement the __invoke() method';
            $nextSteps[] = '  2. Create the corresponding Use Case or Command Handler';
            $nextSteps[] = '  3. Create the Twig template (if needed)';
            $nextSteps[] = '  Tip: Use --with-workflow to generate Form, UseCase, Command, and Input';
        }

        $io->text($nextSteps);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
