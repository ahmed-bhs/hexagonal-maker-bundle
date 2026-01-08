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
use AhmedBhs\HexagonalMakerBundle\Generator\HexagonalGenerator;

final class MakeUseCaseTest extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:use-case-test';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Use Case Test (Application Layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>blog/post</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The use case name (e.g. <fg=yellow>CreatePost</>)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Use Case Test');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');

        $this->generator->generateUseCaseTest($path, $name);

        $this->writeSuccessMessage($io);
        $io->text([
            'Next steps:',
            '  1. Implement test methods for your use case',
            '  2. Add data providers for edge cases',
            '  3. Run tests: vendor/bin/phpunit',
        ]);


        // Write all changes to disk
        $generator->writeChanges();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
