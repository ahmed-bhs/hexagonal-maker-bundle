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

final class MakeValueObject extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:value-object';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Domain Value Object';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The value object name (e.g. <fg=yellow>Email</>)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Domain Value Object');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');

        $this->generator->generateValueObject($path, $name);

        $this->writeSuccessMessage($io);

        // Write all changes to disk
        $generator->writeChanges();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
