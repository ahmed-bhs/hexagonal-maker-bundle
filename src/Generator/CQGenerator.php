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

namespace AhmedBhs\HexagonalMakerBundle\Generator;

use Symfony\Bundle\MakerBundle\Generator;

class CQGenerator
{
    private Generator $generator;
    private string $rootDir;
    private string $skeletonDir;
    private string $rootNamespace;

    public function __construct(Generator $generator, string $rootDir, string $skeletonDir, string $rootNamespace)
    {
        $this->generator = $generator;
        $this->rootDir = $rootDir;
        $this->skeletonDir = $skeletonDir;
        $this->rootNamespace = $rootNamespace;
    }

    public function generateCommand(string $namespacePath, string $name, bool $withFactory, bool $withTests = false, array $properties = []): void
    {
        $path = new NamespacePath($namespacePath, $this->rootNamespace);
        $name = NamespacePath::normalize($name);

        $this->generateApplicationCommand($path, $name, $properties);

        if ($withFactory) {
            $this->generateApplicationCommandHandlerWithFactory($path, $name, $properties);
            $this->generateApplicationFactory($path, $name);
        } else {
            $this->generateApplicationCommandHandler($path, $name, $properties);
        }

        $this->generator->writeChanges();
    }

    public function generateQuery(string $namespacePath, string $name): void
    {
        $path = new NamespacePath($namespacePath, $this->rootNamespace);
        $name = NamespacePath::normalize($name);

        $this->generateApplicationQuery($path, $name);
        $this->generateApplicationQueryHandler($path, $name);
        $this->generateApplicationQueryResponse($path, $name);

        $this->generator->writeChanges();
    }

    private function generateApplicationCommand(NamespacePath $path, string $name, array $properties = []): void
    {
        $className = $name.'Command';

        // Convert PropertyConfig objects to arrays for template
        $propertyData = array_map(fn($prop) => $prop->toArray(), $properties);

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Command/Command.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'command_type' => $className,
                'command_name' => strtolower($className),
                'properties' => $propertyData,
            ]
        );
    }

    private function generateApplicationCommandHandler(NamespacePath $path, string $name, array $properties = []): void
    {
        $className = $name.'CommandHandler';

        // Convert PropertyConfig objects to arrays for template
        $propertyData = array_map(fn($prop) => $prop->toArray(), $properties);

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Command/CommandHandler.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'command_type' => $name,
                'command_name' => strtolower($name),
                'properties' => $propertyData,
            ]
        );
    }

    private function generateApplicationCommandHandlerWithFactory(NamespacePath $path, string $name): void
    {
        $aggregateShortName = $path->toShortClassName();
        $className = $name.'CommandHandler';

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Command/CommandHandlerWithFactory.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'command_type' => $name,
                'command_name' => strtolower($name),
                'aggregate_type' => $aggregateShortName,
                'aggregate_name' => strtolower($aggregateShortName),
            ]
        );
    }

    private function generateApplicationFactory(NamespacePath $path, string $name): void
    {
        $aggregateShortName = $path->toShortClassName();
        $className = $aggregateShortName.'Factory';

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Command/Factory.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'command_type' => $name,
                'command_name' => strtolower($name),
                'aggregate_namespace' => $path->toNamespace('\\Domain\\Model'),
                'aggregate_type' => $aggregateShortName,
                'aggregate_name' => strtolower($aggregateShortName),
            ]
        );
    }

    private function generateApplicationQuery(NamespacePath $path, string $name): void
    {
        $className = $name.'Query';

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Query/Query.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'query_type' => $className,
                'query_name' => strtolower($className),
            ]
        );
    }

    private function generateApplicationQueryHandler(NamespacePath $path, string $name): void
    {
        $className = $name.'QueryHandler';

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Query/QueryHandler.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'query_type' => $name,
                'query_name' => strtolower($name),
            ]
        );
    }

    private function generateApplicationQueryResponse(NamespacePath $path, string $name): void
    {
        $aggregateShortName = $path->toShortClassName();
        $className = $name.'Response';

        $this->generator->generateFile(
            $this->rootDir.'/'.$path->normalizedValue().'/Application/'.$name.'/'.$className.'.php',
            $this->skeletonDir.'/src/Module/Application/Query/Response.tpl.php',
            [
                'root_namespace' => $this->rootNamespace,
                'namespace' => $path->toNamespace('\\Application\\'.$name),
                'class_name' => $className,
                'aggregate_namespace' => $path->toNamespace('\\Domain\\Model'),
                'aggregate_type' => $aggregateShortName,
                'aggregate_name' => strtolower($aggregateShortName),
            ]
        );
    }
}
