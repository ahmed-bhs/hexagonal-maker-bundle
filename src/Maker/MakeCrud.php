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

final class MakeCrud extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:crud';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a complete CRUD module (Entity, Repository, UseCases, Controllers, Forms, Tests)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>blog/post</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The entity name (e.g. <fg=yellow>Post</>)')
            ->addOption('route-prefix', null, InputOption::VALUE_REQUIRED, 'Route prefix (e.g. <fg=yellow>/posts</>)')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Also generate all tests')
            ->addOption('with-id-vo', null, InputOption::VALUE_NONE, 'Use ID ValueObject for entity')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('🚀 Creating Complete CRUD Module');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $routePrefix = $input->getOption('route-prefix') ?? '/' . strtolower($name) . 's';
        $withTests = $input->getOption('with-tests');
        $withIdVo = $input->getOption('with-id-vo');

        $io->section('Step 1/5: Generating Domain Layer...');

        // 1. Entity + Repository + ID ValueObject (Domain + Infrastructure)
        $this->generator->generateEntity($path, $name);
        $this->generator->generateRepository($path, $name);

        $generatedFiles = [
            '  📦 Domain Layer:',
            '    - ' . $name . '.php (Entity)',
        ];

        if ($withIdVo) {
            $this->generator->generateValueObject($path, $name . 'Id');
            $generatedFiles[] = '    - ' . $name . 'Id.php (ValueObject)';
        }

        $generatedFiles[] = '    - ' . $name . 'RepositoryInterface.php (Port)';
        $generatedFiles[] = '  🔧 Infrastructure Layer:';
        $generatedFiles[] = '    - Doctrine' . $name . 'Repository.php';
        $generatedFiles[] = '    - ' . $name . '.orm.yml';

        $io->section('Step 2/5: Generating Application Layer (5 UseCases)...');

        // 2. Application Layer: 5 UseCases with Commands and Inputs
        $useCases = [
            'Create' => 'Create new ' . strtolower($name),
            'Update' => 'Update existing ' . strtolower($name),
            'Delete' => 'Delete ' . strtolower($name),
            'Get' => 'Get single ' . strtolower($name),
            'List' => 'List all ' . strtolower($name) . 's',
        ];

        $generatedFiles[] = '  🎯 Application Layer:';

        foreach ($useCases as $action => $description) {
            $this->generator->generateUseCase($path, $action . $name);
            $this->generator->generateCommand($path, $action . $name, false);
            $this->generator->generateInput($path, $action . $name . 'Input');
            $generatedFiles[] = '    - ' . $action . $name . 'UseCase.php';

            if ($withTests) {
                $this->generator->generateUseCaseTest($path, $action . $name);
                $generatedFiles[] = '    - ' . $action . $name . 'Test.php';
            }
        }

        $io->section('Step 3/5: Generating UI Web Layer (Controllers + Forms)...');

        // 3. UI Web Layer: Controllers + Forms
        $generatedFiles[] = '  🌐 UI Web Layer:';

        // Create/Update controllers with forms
        foreach (['Create', 'Update'] as $action) {
            $route = $routePrefix . ($action === 'Create' ? '/new' : '/{id}/edit');
            $this->generator->generateController($path, $action . $name, $route);
            $generatedFiles[] = '    - ' . $action . $name . 'Controller.php';

            if ($withTests) {
                $this->generator->generateControllerTest($path, $action . $name, $route);
                $generatedFiles[] = '    - ' . $action . $name . 'ControllerTest.php';
            }
        }

        // Generate single form for Create/Update
        $this->generator->generateForm($path, $name);
        $generatedFiles[] = '    - ' . $name . 'Type.php (Form)';

        // Delete/Show/List controllers
        $simpleControllers = [
            'Delete' => $routePrefix . '/{id}/delete',
            'Show' => $routePrefix . '/{id}',
            'List' => $routePrefix,
        ];

        foreach ($simpleControllers as $action => $route) {
            $this->generator->generateController($path, $action . $name, $route);
            $generatedFiles[] = '    - ' . $action . $name . 'Controller.php';

            if ($withTests) {
                $this->generator->generateControllerTest($path, $action . $name, $route);
                $generatedFiles[] = '    - ' . $action . $name . 'ControllerTest.php';
            }
        }

        $io->section('Step 4/5: Counting generated files...');

        $fileCount = 0;
        $fileCount += 3; // Entity + Repository Interface + Doctrine Adapter + YAML
        if ($withIdVo) $fileCount += 1;
        $fileCount += 5 * 3; // 5 UseCases * (UseCase + Command + Input)
        $fileCount += 5; // 5 Controllers
        $fileCount += 1; // 1 Form

        if ($withTests) {
            $fileCount += 5; // 5 UseCase tests
            $fileCount += 5; // 5 Controller tests
        }

        $io->section('Step 5/5: Summary');

        $io->success(sprintf('✅ Complete CRUD module generated successfully! (%d files)', $fileCount));
        $io->text($generatedFiles);

        $io->section('📋 Next Steps');

        $nextSteps = [
            '1️⃣  Add properties to your Entity: src/Module/' . ucfirst($path) . '/Domain/' . $name . '.php',
            '2️⃣  Complete Doctrine mapping: config/doctrine/' . ucfirst($path) . '/Domain/' . $name . '.orm.yml',
            '3️⃣  Configure form fields: src/Module/' . ucfirst($path) . '/UI/Http/Web/Form/' . $name . 'Type.php',
            '4️⃣  Implement UseCase business logic in Application layer',
            '5️⃣  Implement Repository methods in Infrastructure layer',
            '6️⃣  Test your CRUD:',
            '     - List: ' . $routePrefix,
            '     - Create: ' . $routePrefix . '/new',
            '     - Show: ' . $routePrefix . '/{id}',
            '     - Edit: ' . $routePrefix . '/{id}/edit',
            '     - Delete: ' . $routePrefix . '/{id}/delete',
        ];

        if ($withTests) {
            $nextSteps[] = '7️⃣  Run tests: vendor/bin/phpunit';
        } else {
            $nextSteps[] = '💡 Tip: Use --with-tests to auto-generate all test files';
        }

        $io->listing($nextSteps);

        $io->note([
            'This CRUD module follows Hexagonal Architecture principles:',
            '  • Domain Layer: Pure business entities (no framework dependencies)',
            '  • Application Layer: Use cases orchestrating business logic',
            '  • Infrastructure Layer: Doctrine adapter for persistence',
            '  • UI Layer: Web controllers and forms for user interaction',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
