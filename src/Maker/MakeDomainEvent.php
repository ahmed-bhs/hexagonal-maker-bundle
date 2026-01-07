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

final class MakeDomainEvent extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:domain-event';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new Domain Event (immutable business event)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>order/payment</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The event name (e.g. <fg=yellow>OrderPlaced</>)')
            ->addOption('with-subscriber', null, InputOption::VALUE_NONE, 'Also generate Event Subscriber (Application layer)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Domain Event');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withSubscriber = $input->getOption('with-subscriber');

        // Generate Domain Event
        $this->generator->generateDomainEvent($path, $name);

        $generatedFiles = ['  - Domain Event: ' . $name . 'Event.php'];

        // Generate Subscriber if requested
        if ($withSubscriber) {
            $io->text('Also generating Event Subscriber...');
            $this->generator->generateEventSubscriber($path, $name, 'application');
            $generatedFiles[] = '  - Event Subscriber: ' . $name . 'Subscriber.php';

            $io->success('Domain Event with Subscriber generated successfully!');
            $io->text(['Generated files:'] + $generatedFiles);
        } else {
            $this->writeSuccessMessage($io);
            $io->text($generatedFiles);
        }

        $nextSteps = [
            'Next steps:',
            '  1. Add event properties in constructor',
            '  2. Dispatch this event from your entities or use cases',
        ];

        if ($withSubscriber) {
            $nextSteps[] = '  3. Implement event handling in ' . $name . 'Subscriber.php';
            $nextSteps[] = '  4. Inject use cases in the subscriber';
        } else {
            $nextSteps[] = '  3. Create event subscribers to react to this event';
            $nextSteps[] = '  Tip: Use --with-subscriber to auto-generate subscriber';
        }

        $io->text($nextSteps);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
