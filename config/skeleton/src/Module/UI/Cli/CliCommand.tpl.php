<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $use_case_namespace ?>\<?= $use_case_class ?>;
use <?= $command_namespace ?>\<?= $command_class ?>;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * UI Layer - CLI Command
 *
 * Part of the UI (User Interface) layer in hexagonal architecture.
 * Provides command-line interface to execute use cases.
 *
 * This is a PRIMARY ADAPTER (driving adapter) that drives the application core.
 */
#[AsCommand(
    name: '<?= $command_name ?>',
    description: '<?= $command_description ?>',
)]
final class <?= $class_name ?> extends Command
{
    public function __construct(
        private readonly <?= $use_case_class ?> $useCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // TODO: Add your arguments and options
            // ->addArgument('arg1', InputArgument::REQUIRED, 'Argument description')
            // ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // TODO: Implement command logic
        // Example:
        //
        // $arg1 = $input->getArgument('arg1');
        // $option1 = $input->getOption('option1');
        //
        // $command = new <?= $command_class ?>(
        //     $arg1,
        //     $option1
        // );
        //
        // try {
        //     $response = $this->useCase->execute($command);
        //
        //     $io->success('Operation completed successfully!');
        //     return Command::SUCCESS;
        //
        // } catch (\DomainException $e) {
        //     $io->error($e->getMessage());
        //     return Command::FAILURE;
        // }

        $io->note('Command not implemented yet');
        return Command::SUCCESS;
    }
}
