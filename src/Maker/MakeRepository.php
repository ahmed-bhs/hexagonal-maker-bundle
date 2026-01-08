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
use AhmedBhs\HexagonalMakerBundle\Config\ServicesConfigUpdater;

final class MakeRepository extends AbstractMaker
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
        return 'make:hexagonal:repository';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Repository port interface and adapter';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The aggregate/entity name (e.g. <fg=yellow>User</>)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Repository (Port + Adapter)');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');

        $this->generator->generateRepository($path, $name);

        // Auto-configure repository binding
        $this->autoConfigureRepositoryBinding($path, $name, $io);

        $this->writeSuccessMessage($io);


        // Write all changes to disk
        $generator->writeChanges();
    }

    /**
     * Auto-configure repository binding in services.yaml
     */
    private function autoConfigureRepositoryBinding(string $path, string $name, ConsoleStyle $io): void
    {
        try {
            // Parse path to get module namespace
            $parts = array_map('ucfirst', explode('/', $path));
            $moduleNamespace = 'App\\' . implode('\\', $parts);

            $interfaceFqcn = $moduleNamespace . '\\Domain\\Port\\' . $name . 'RepositoryInterface';
            $classFqcn = $moduleNamespace . '\\Infrastructure\\Persistence\\Doctrine\\Doctrine' . $name . 'Repository';

            $servicesUpdater = new ServicesConfigUpdater($this->projectDir);

            if ($servicesUpdater->add(['interface' => $interfaceFqcn, 'class' => $classFqcn])) {
                $io->text('  <fg=green>✓</> Auto-configured repository binding in services.yaml');
            }
        } catch (\Exception $e) {
            $io->warning('Could not auto-configure repository binding: ' . $e->getMessage());
            $io->text('  Please add the binding manually in config/services.yaml');
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
