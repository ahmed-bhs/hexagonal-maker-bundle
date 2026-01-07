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

final class MakeUseCase extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:use-case';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Use Case (Application Service)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The use case name (e.g. <fg=yellow>CreateUser</>)')
            ->addOption('with-test', null, InputOption::VALUE_NONE, 'Also generate Use Case Test')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Use Case');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withTest = $input->getOption('with-test');

        // Generate Use Case
        $this->generator->generateUseCase($path, $name);

        $generatedFiles = ['  - UseCase: ' . $name . 'UseCase.php'];

        // Generate Test if requested
        if ($withTest) {
            $io->text('Also generating Use Case Test...');
            $this->generator->generateUseCaseTest($path, $name);
            $generatedFiles[] = '  - Test: ' . $name . 'Test.php';

            $io->success('Use Case with Test generated successfully!');
            $io->text(['Generated files:'] + $generatedFiles);
        } else {
            $this->writeSuccessMessage($io);
            $io->text($generatedFiles);
        }

        $nextSteps = [
            'Next steps:',
            '  1. Implement the execute() method logic',
            '  2. Create the corresponding Command/Query',
            '  3. Create the Response DTO',
            '  4. Create the Repository interface',
        ];

        if ($withTest) {
            $nextSteps[] = '  5. Implement test methods in ' . $name . 'Test.php';
            $nextSteps[] = '  6. Add data providers for edge cases';
        } else {
            $nextSteps[] = '  Tip: Use --with-test to auto-generate the test file';
        }

        $io->text($nextSteps);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
