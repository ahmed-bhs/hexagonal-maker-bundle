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

final class MakeControllerTest extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:controller-test';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Controller Test (UI Layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>blog/post</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g. <fg=yellow>CreatePost</>)')
            ->addArgument('route', InputArgument::REQUIRED, 'The route path (e.g. <fg=yellow>/posts/create</>)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Controller Test');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $route = $input->getArgument('route');

        $this->generator->generateControllerTest($path, $name, $route);

        $this->writeSuccessMessage($io);
        $io->text([
            'Next steps:',
            '  1. Implement HTTP request tests',
            '  2. Add form submission tests',
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
