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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use AhmedBhs\HexagonalMakerBundle\Generator\HexagonalGenerator;
use AhmedBhs\HexagonalMakerBundle\Generator\PropertyConfig;
use AhmedBhs\HexagonalMakerBundle\Config\DoctrineConfigUpdater;
use AhmedBhs\HexagonalMakerBundle\Config\ServicesConfigUpdater;

final class MakeEntity extends AbstractMaker
{
    private HexagonalGenerator $generator;
    private string $projectDir;

    public function __construct(HexagonalGenerator $generator, string $projectDir)
    {
        $this->generator = $generator;
        $this->projectDir = $projectDir;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:entity';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Domain Entity';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The entity name (e.g. <fg=yellow>User</>)')
            ->addOption('with-repository', null, InputOption::VALUE_NONE, 'Also generate Repository (Port + Doctrine Adapter + YAML mapping)')
            ->addOption('with-id-vo', null, InputOption::VALUE_NONE, 'Also generate ID ValueObject')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED, 'Comma-separated properties (e.g. <fg=yellow>name:string,age:int(0,150),email:email:unique</>)')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Skip interactive property prompts')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Domain Entity');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withRepository = $input->getOption('with-repository');
        $withIdVo = $input->getOption('with-id-vo');
        $noInteraction = $input->getOption('no-interaction');

        // Parse properties from option or ask interactively
        $properties = $this->getProperties($input, $io, $noInteraction);

        // Generate Entity with properties
        $this->generator->generateEntity($path, $name, $properties);

        // Detect mapping file extension
        $mappingExt = $this->isDoctrine3OrHigher() ? 'xml' : 'yml';
        $generatedFiles = ['  - Entity: ' . $name . '.php', '  - Doctrine Mapping: ' . $name . '.orm.' . $mappingExt];

        // Auto-configure Doctrine and Services
        $this->autoConfigureDoctrine($path, $io);
        $this->autoConfigureServices($io);

        // Generate Repository if requested
        if ($withRepository) {
            $io->text('Also generating Repository...');
            $this->generator->generateRepository($path, $name, $properties);
            $generatedFiles[] = '  - Repository Port: ' . $name . 'RepositoryInterface.php';
            $generatedFiles[] = '  - Doctrine Adapter: Doctrine' . $name . 'Repository.php';
        }

        // Generate ID ValueObject if requested
        if ($withIdVo) {
            $io->text('Also generating ID ValueObject...');
            $this->generator->generateValueObject($path, $name . 'Id');
            $generatedFiles[] = '  - ID ValueObject: ' . $name . 'Id.php';
        }

        if ($withRepository || $withIdVo) {
            $io->success('Entity with additional components generated successfully!');
            $io->text(['Generated files:'] + $generatedFiles);
        } else {
            $this->writeSuccessMessage($io);
            $io->text($generatedFiles);
        }

        if (empty($properties)) {
            $nextSteps = ['Next steps:', '  1. Add properties to your entity'];

            if ($withIdVo) {
                $nextSteps[] = '  2. Use ' . $name . 'Id as the ID type in your entity';
            }

            if ($withRepository) {
                $nextSteps[] = ($withIdVo ? '  3' : '  2') . '. Complete the Doctrine ORM mapping in ' . $name . '.orm.yml';
                $nextSteps[] = ($withIdVo ? '  4' : '  3') . '. Implement repository methods';
            } else {
                $nextSteps[] = '  2. Complete the Doctrine ORM mapping';
                $nextSteps[] = '  Tip: Use --with-repository to auto-generate Repository';
            }

            $io->text($nextSteps);
        } else {
            $io->text([
                'Next steps:',
                '  1. Review generated entity properties and business logic',
                '  2. Add additional domain methods as needed',
                '  Tip: Entity is ready to use with ' . count($properties) . ' properties configured!',
            ]);
        }

        // Write all changes to disk
        $generator->writeChanges();
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
        $io->section('Entity Properties Configuration');
        $io->text([
            'Add properties to your entity (press Enter with empty name to finish)',
            'Format examples:',
            '  - Simple: name',
            '  - With type: email:email',
            '  - With constraints: age:int(0,150)',
            '  - With options: username:string:unique',
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

            // Ask for type
            $typeQuestion = new ChoiceQuestion(
                '  Property type:',
                ['string', 'int', 'bool', 'float', 'datetime', 'date', 'email', 'text'],
                'string'
            );
            $propertyType = $helper->ask($input, $io->getOutput(), $typeQuestion);

            // Ask for constraints based on type
            $nullable = false;
            $unique = false;
            $minLength = null;
            $maxLength = null;
            $min = null;
            $max = null;

            if ($propertyType === 'string' || $propertyType === 'email' || $propertyType === 'text') {
                $requiredQuestion = new ConfirmationQuestion('  Required? (y/n) [<fg=yellow>y</>]: ', true);
                $nullable = !$helper->ask($input, $io->getOutput(), $requiredQuestion);

                if ($propertyType === 'string') {
                    $lengthQuestion = new Question('  Max length [<fg=yellow>255</>]: ', 255);
                    $maxLength = $helper->ask($input, $io->getOutput(), $lengthQuestion);
                }
            }

            if (in_array($propertyType, ['int', 'float'])) {
                $minQuestion = new Question('  Min value (optional): ', null);
                $min = $helper->ask($input, $io->getOutput(), $minQuestion);

                $maxQuestion = new Question('  Max value (optional): ', null);
                $max = $helper->ask($input, $io->getOutput(), $maxQuestion);
            }

            if (in_array($propertyType, ['string', 'email', 'int'])) {
                $uniqueQuestion = new ConfirmationQuestion('  Unique? (y/n) [<fg=yellow>n</>]: ', false);
                $unique = $helper->ask($input, $io->getOutput(), $uniqueQuestion);
            }

            $properties[] = new PropertyConfig(
                name: $propertyName,
                type: $propertyType,
                nullable: $nullable,
                unique: $unique,
                minLength: $minLength,
                maxLength: $maxLength,
                min: $min,
                max: $max
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
     * Example: "nom:string(3,100),age:int(0,150)" => ["nom:string(3,100)", "age:int(0,150)"]
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

    /**
     * Auto-configure Doctrine mapping for the module
     */
    private function autoConfigureDoctrine(string $path, ConsoleStyle $io): void
    {
        try {
            // Parse path to get module info
            // Example: "cadeau/attribution" => ["Cadeau", "Attribution"]
            $parts = array_map('ucfirst', explode('/', $path));
            $moduleName = implode('', $parts);

            $doctrineUpdater = new DoctrineConfigUpdater($this->projectDir);

            // Check Doctrine ORM version to determine mapping type
            $useXml = $this->isDoctrine3OrHigher();
            $mappingType = $useXml ? 'xml' : 'yml';

            $config = [
                'mapping_name' => $moduleName,
                'type' => $mappingType,
                'dir' => '%kernel.project_dir%/src/' . implode('/', $parts) . '/Infrastructure/Persistence/Doctrine/Orm/Mapping',
                'prefix' => 'App\\' . implode('\\', $parts) . '\\Domain\\Model',
                'alias' => $moduleName,
            ];

            if ($doctrineUpdater->add($config)) {
                $io->text('  <fg=green>✓</> Auto-configured Doctrine mapping in doctrine.yaml');
            }
        } catch (\Exception $e) {
            $io->warning('Could not auto-configure Doctrine: ' . $e->getMessage());
            $io->text('  Please add the mapping manually in config/packages/doctrine.yaml');
        }
    }

    /**
     * Auto-configure Services (Domain exclusions)
     */
    private function autoConfigureServices(ConsoleStyle $io): void
    {
        try {
            $servicesUpdater = new ServicesConfigUpdater($this->projectDir);

            if ($servicesUpdater->addDomainExclusions()) {
                $io->text('  <fg=green>✓</> Auto-configured Domain exclusions in services.yaml');
            }
        } catch (\Exception $e) {
            $io->warning('Could not auto-configure Services: ' . $e->getMessage());
            $io->text('  Please exclude Domain/Model and Domain/ValueObject manually');
        }
    }

    /**
     * Check if Doctrine ORM 3.x or higher is installed
     */
    private function isDoctrine3OrHigher(): bool
    {
        $composerPath = $this->projectDir . '/composer.json';

        if (!file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $ormVersion = $composer['require']['doctrine/orm'] ?? $composer['require-dev']['doctrine/orm'] ?? null;

        if (!$ormVersion) {
            return false;
        }

        return str_contains($ormVersion, '^3.') || str_contains($ormVersion, '^4.');
    }
}
