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
use Symfony\Component\Console\Input\InputInterface;
use AhmedBhs\HexagonalMakerBundle\Generator\HexagonalGenerator;

final class MakeTestConfig extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:test-config';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates complete test configuration (phpunit.xml.dist, bootstrap, .env.test, etc.)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        // No arguments needed
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Setting up Complete Test Configuration');

        $this->generator->generateTestConfiguration();

        $this->writeSuccessMessage($io);
        $io->text([
            'Next steps:',
            '  1. Review generated configuration files',
            '  2. Update .env.test with your database credentials',
            '  3. Run: composer install (if not done)',
            '  4. Run: composer tests',
        ]);

        $io->note([
            'Generated files:',
            '  - phpunit.xml.dist',
            '  - tests/bootstrap.php',
            '  - .env.test',
            '  - config/packages/test/ (when@test configurations)',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
