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
use Symfony\Component\Form\AbstractType;
use AhmedBhs\HexagonalMakerBundle\Generator\HexagonalGenerator;

final class MakeForm extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:form';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Symfony Form Type (UI Layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The form name (e.g. <fg=yellow>User</>)')
            ->addOption('with-command', null, InputOption::VALUE_NONE, 'Also generate Command and Input DTO')
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'Action name for Command (e.g. <fg=yellow>Create</> for CreateUserCommand)', null)
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Symfony Form Type');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withCommand = $input->getOption('with-command');
        $action = $input->getOption('action');

        // Generate Form Type
        $this->generator->generateForm($path, $name);

        // Generate Command and Input if requested
        if ($withCommand) {
            $actionName = $action ?? 'Create' . $name;

            $io->text('Also generating Command and Input DTO...');

            // Generate Input DTO
            $this->generator->generateInput($path, $actionName . 'Input');

            // Generate Command
            $this->generator->generateCommand($path, $actionName, false);

            $io->success('Form, Command, and Input DTO generated successfully!');
        } else {
            $this->writeSuccessMessage($io);
        }

        $nextSteps = [
            'Next steps:',
            '  1. Add form fields in buildForm() method',
            '  2. Configure options in configureOptions() method',
        ];

        if ($withCommand) {
            $nextSteps[] = '  3. Map form data to Command in your controller';
            $nextSteps[] = '  4. Add validation constraints to Input DTO';
        } else {
            $nextSteps[] = '  3. Use the form in your controller';
            $nextSteps[] = '  Tip: Use --with-command to auto-generate Command and Input DTO';
        }

        $io->text($nextSteps);


        // Write all changes to disk
        $generator->writeChanges();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            AbstractType::class,
            'symfony/form'
        );
    }
}
