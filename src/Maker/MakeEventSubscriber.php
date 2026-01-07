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

final class MakeEventSubscriber extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:event-subscriber';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Event Subscriber (Application or Infrastructure layer)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>order/payment</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The subscriber name (e.g. <fg=yellow>OrderPlaced</>)')
            ->addOption('layer', 'l', InputOption::VALUE_REQUIRED, 'The layer: application or infrastructure', 'application')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Event Subscriber');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $layer = $input->getOption('layer');

        // Validate layer
        if (!in_array($layer, ['application', 'infrastructure'])) {
            $io->error('Layer must be either "application" or "infrastructure"');
            return;
        }

        $this->generator->generateEventSubscriber($path, $name, $layer);

        $this->writeSuccessMessage($io);

        if ($layer === 'application') {
            $io->text([
                'Next steps (Application Layer):',
                '  1. Inject use cases or services in constructor',
                '  2. Implement event handling logic',
                '  3. Orchestrate business workflows',
            ]);
        } else {
            $io->text([
                'Next steps (Infrastructure Layer):',
                '  1. Inject technical services (logger, cache, etc.)',
                '  2. Implement technical concerns (logging, monitoring)',
                '  3. Handle framework-specific events',
            ]);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
