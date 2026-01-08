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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use AhmedBhs\HexagonalMakerBundle\Generator\CQGenerator;
use AhmedBhs\HexagonalMakerBundle\Generator\PropertyConfig;

final class MakeCommand extends AbstractMaker
{
    private CQGenerator $commandGenerator;

    public function __construct(CQGenerator $commandGenerator)
    {
        $this->commandGenerator = $commandGenerator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:command';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Command';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path of the new Command (e.g. <fg=yellow>catalog/listing</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The Command name (e.g. <fg=yellow>publish</>)')
            ->addOption('factory', null, InputOption::VALUE_NONE, 'Generate with factory')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Generate unit and integration tests')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED, 'Comma-separated properties (e.g. <fg=yellow>habitantId:string,cadeauId:string</>)')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Skip interactive property prompts')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'Command pattern: create, update, delete (auto-detected if not provided)')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Entity name for auto-generation (e.g. <fg=yellow>Habitant</>)')
            //->setHelp(file_get_contents(__DIR__.'/../help/MakeCommand.txt'))
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Command');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $factory = $input->getOption('factory');
        $withTests = $input->getOption('with-tests');
        $noInteraction = $input->getOption('no-interaction');
        $entity = $input->getOption('entity');

        // Parse properties from option or ask interactively
        $properties = $this->getProperties($input, $io, $noInteraction);

        // Auto-detect or use provided pattern
        $pattern = $input->getOption('pattern');
        if (!$pattern) {
            $pattern = $this->detectPattern($name);
        }

        // Generate metadata for handler
        $metadata = [];
        if ($pattern && $entity) {
            $metadata['pattern'] = $pattern;
            $metadata['entityName'] = $entity;
            $io->text(sprintf('Detected pattern: <fg=green>%s</> for entity: <fg=green>%s</>', $pattern, $entity));
        }

        $this->commandGenerator->generateCommand($path, $name, $factory, $withTests, $properties, $metadata);

        $this->writeSuccessMessage($io);

        if ($withTests) {
            $io->success('Command, Handler and Tests generated successfully!');
        }

        if (!empty($properties)) {
            $io->text([
                'Generated with ' . count($properties) . ' properties',
                'Next: Implement the business logic in the CommandHandler',
            ]);
        }

        // Write all changes to disk
        $generator->writeChanges();
    }

    private function detectPattern(string $commandName): ?string
    {
        $lower = strtolower($commandName);

        if (str_starts_with($lower, 'create') || str_starts_with($lower, 'creer')) {
            return 'create';
        }

        if (str_starts_with($lower, 'update') || str_starts_with($lower, 'modifier') || str_starts_with($lower, 'mettre')) {
            return 'update';
        }

        if (str_starts_with($lower, 'delete') || str_starts_with($lower, 'supprimer') || str_starts_with($lower, 'remove')) {
            return 'delete';
        }

        return null;
    }

    /**
     * @return PropertyConfig[]
     */
    private function getProperties(InputInterface $input, ConsoleStyle $io, bool $noInteraction): array
    {
        $properties = [];

        // Parse from --properties option
        if ($propertiesOption = $input->getOption('properties')) {
            $propertyStrings = $this->splitProperties($propertiesOption);
            foreach ($propertyStrings as $propertyString) {
                $properties[] = PropertyConfig::fromString(trim($propertyString));
            }
            return $properties;
        }

        // Skip if no-interaction
        if ($noInteraction) {
            return [];
        }

        // Interactive mode
        $io->section('Command Properties Configuration');
        $io->text([
            'Add properties to your command (press Enter with empty name to finish)',
            'Commands are DTOs that carry data for write operations.',
            '',
        ]);

        $helper = $io->getQuestionHelper();
        $propertyCount = 0;

        while (true) {
            $io->newLine();
            $propertyCount++;

            // Ask for property name
            $nameQuestion = new Question(sprintf('  Property name (or press Enter to finish) [<fg=yellow>%d</>]: ', $propertyCount), '');
            $propertyName = $helper->ask($input, $io->getOutput(), $nameQuestion);

            if (empty($propertyName)) {
                break;
            }

            // Ask for type (simpler for commands - mostly string, int, bool)
            $typeQuestion = new ChoiceQuestion(
                '  Property type:',
                ['string', 'int', 'bool', 'float', 'array'],
                'string'
            );
            $propertyType = $helper->ask($input, $io->getOutput(), $typeQuestion);

            $properties[] = new PropertyConfig(
                name: $propertyName,
                type: $propertyType
            );

            $io->text('  <fg=green>✓</> Property "' . $propertyName . '" added');
        }

        if (!empty($properties)) {
            $io->newLine();
            $io->success(count($properties) . ' properties configured!');
        }

        return $properties;
    }

    /**
     * Split properties string by comma, but respect parentheses
     */
    private function splitProperties(string $propertiesString): array
    {
        $properties = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($propertiesString); $i++) {
            $char = $propertiesString[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                if ($current !== '') {
                    $properties[] = trim($current);
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $properties[] = trim($current);
        }

        return $properties;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
