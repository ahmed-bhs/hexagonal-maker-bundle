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

final class MakeEntity extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
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
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Domain Entity');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withRepository = $input->getOption('with-repository');
        $withIdVo = $input->getOption('with-id-vo');

        // Generate Entity (includes YAML mapping automatically)
        $this->generator->generateEntity($path, $name);

        $generatedFiles = ['  - Entity: ' . $name . '.php', '  - Doctrine Mapping: ' . $name . '.orm.yml'];

        // Generate Repository if requested
        if ($withRepository) {
            $io->text('Also generating Repository...');
            $this->generator->generateRepository($path, $name);
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
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
