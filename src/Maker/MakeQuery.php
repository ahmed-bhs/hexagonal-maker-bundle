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
use AhmedBhs\HexagonalMakerBundle\Generator\CQGenerator;
use AhmedBhs\HexagonalMakerBundle\Generator\PropertyConfig;

final class MakeQuery extends AbstractMaker
{
    private CQGenerator $commandGenerator;

    public function __construct(CQGenerator $commandGenerator)
    {
        $this->commandGenerator = $commandGenerator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:query';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Query';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path of the new Query (e.g. <fg=yellow>catalog/listing</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The Query name (e.g. <fg=yellow>find</>)')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Entity name for the response (e.g. <fg=yellow>Habitant</>)')
            ->addOption('collection', null, InputOption::VALUE_NONE, 'Response will contain a collection of entities')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED, 'Custom response properties (e.g. <fg=yellow>id:string,nom:string</>)')
            //->setHelp(file_get_contents(__DIR__.'/../help/MakeCommand.txt'))
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Query');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $entity = $input->getOption('entity');
        $isCollection = $input->getOption('collection');
        $propertiesOption = $input->getOption('properties');

        // Parse response properties if provided
        $properties = [];
        if ($propertiesOption) {
            $propertyStrings = $this->splitProperties($propertiesOption);
            foreach ($propertyStrings as $propertyString) {
                $properties[] = PropertyConfig::fromString($propertyString);
            }
        }

        // Build metadata for response generation
        $metadata = [];
        if ($entity) {
            $metadata['entityName'] = $entity;
            $metadata['isCollection'] = $isCollection;
            $io->text(sprintf('Generating query for entity: <fg=green>%s</> (collection: %s)', $entity, $isCollection ? 'yes' : 'no'));
        } elseif (!empty($properties)) {
            $io->text(sprintf('Generating query with %d custom properties', count($properties)));
        }

        $this->commandGenerator->generateQuery($path, $name, $properties, $metadata);

        $this->writeSuccessMessage($io);

        // Write all changes to disk
        $generator->writeChanges();
    }

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
