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

final class MakeMessageHandler extends AbstractMaker
{
    private HexagonalGenerator $generator;

    public function __construct(HexagonalGenerator $generator)
    {
        $this->generator = $generator;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:message-handler';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a Message Handler for async processing (Symfony Messenger)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('path', InputArgument::REQUIRED, 'The namespace path (e.g. <fg=yellow>user/account</>)')
            ->addArgument('name', InputArgument::REQUIRED, 'The message name (e.g. <fg=yellow>SendWelcomeEmail</>)')
            ->addOption('with-message', null, InputOption::VALUE_NONE, 'Also generate Message class (DTO)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('Creating new Message Handler for Async Processing');
        $path = $input->getArgument('path');
        $name = $input->getArgument('name');
        $withMessage = $input->getOption('with-message');

        // Generate Message Handler
        $this->generator->generateMessageHandler($path, $name);

        $generatedFiles = ['  - Message Handler: ' . $name . 'Handler.php (Infrastructure/Messaging/Handler/)'];

        // Generate Message if requested
        if ($withMessage) {
            $io->text('Also generating Message class...');
            $this->generator->generateMessage($path, $name);
            $generatedFiles[] = '  - Message: ' . $name . 'Message.php (Application/Message/)';

            $io->success('Message Handler with Message class generated successfully!');
            $io->text(['Generated files:'] + $generatedFiles);
        } else {
            $this->writeSuccessMessage($io);
            $io->text($generatedFiles);
        }

        $nextSteps = [
            'Next steps:',
            '  1. Add constructor dependencies (use cases, services, logger)',
            '  2. Implement __invoke() method with async logic',
        ];

        if ($withMessage) {
            $nextSteps[] = '  3. Add properties to ' . $name . 'Message.php';
            $nextSteps[] = '  4. Dispatch message: $messageBus->dispatch(new ' . $name . 'Message(...))';
            $nextSteps[] = '  5. Configure messenger.yaml for async transport';
            $nextSteps[] = '  6. Start worker: bin/console messenger:consume async';
        } else {
            $nextSteps[] = '  3. Create your Message class manually or use --with-message';
            $nextSteps[] = '  Tip: Use --with-message to auto-generate Message DTO';
        }

        $io->text($nextSteps);

        $io->note([
            'Message Handler Pattern (Hexagonal Architecture):',
            '  • Handler in Infrastructure (Secondary Adapter for async)',
            '  • Message in Application (DTO for async communication)',
            '  • Use for: Emails, background jobs, event processing, API sync',
        ]);

        $io->text([
            '<fg=yellow>📋 Configuration Example (config/packages/messenger.yaml):</>',
            '',
            'framework:',
            '    messenger:',
            '        transports:',
            '            async: "%env(MESSENGER_TRANSPORT_DSN)%"',
            '        routing:',
            '            \'App\\Module\\' . ucfirst(str_replace('/', '\\', $path)) . '\\Application\\Message\\' . $name . 'Message\': async',
        ]);


        // Write all changes to disk
        $generator->writeChanges();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // no-op
    }
}
