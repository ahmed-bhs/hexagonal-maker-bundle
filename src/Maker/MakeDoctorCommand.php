<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Diagnostic command to check hexagonal architecture configuration.
 *
 * Verifies:
 * - Doctrine ORM version and configuration
 * - Messenger buses configuration
 * - Service bindings
 * - Routes configuration
 * - Required packages
 */
final class MakeDoctorCommand extends AbstractMaker
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public static function getCommandName(): string
    {
        return 'make:hexagonal:doctor';
    }

    public static function getCommandDescription(): string
    {
        return 'Diagnose hexagonal architecture configuration and detect issues';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command->setHelp(<<<'HELP'
The <info>%command.name%</info> command diagnoses your hexagonal architecture setup:

  <info>php %command.full_name%</info>

It checks:
  • Doctrine ORM version and mappings configuration
  • Messenger buses (command.bus, query.bus) configuration
  • Repository interface bindings in services.yaml
  • Routes configuration for hexagonal controllers
  • Required packages installation

And suggests fixes for any issues found.
HELP
            );
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // No dependencies required
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $io->title('🏥 Hexagonal Architecture Doctor');
        $io->writeln('Diagnosing your configuration...');
        $io->newLine();

        $hasErrors = false;
        $hasWarnings = false;

        // Check 1: Doctrine ORM
        $io->section('📦 Checking Doctrine ORM');
        $doctrineCheck = $this->checkDoctrine($io);
        if ($doctrineCheck === 'error') {
            $hasErrors = true;
        } elseif ($doctrineCheck === 'warning') {
            $hasWarnings = true;
        }

        // Check 2: Messenger
        $io->section('📨 Checking Messenger');
        $messengerCheck = $this->checkMessenger($io);
        if ($messengerCheck === 'error') {
            $hasErrors = true;
        } elseif ($messengerCheck === 'warning') {
            $hasWarnings = true;
        }

        // Check 3: Services
        $io->section('🔧 Checking Services Configuration');
        $servicesCheck = $this->checkServices($io);
        if ($servicesCheck === 'error') {
            $hasErrors = true;
        } elseif ($servicesCheck === 'warning') {
            $hasWarnings = true;
        }

        // Check 4: Routes
        $io->section('🛣️ Checking Routes Configuration');
        $routesCheck = $this->checkRoutes($io);
        if ($routesCheck === 'error') {
            $hasErrors = true;
        } elseif ($routesCheck === 'warning') {
            $hasWarnings = true;
        }

        // Check 5: Required packages
        $io->section('📚 Checking Required Packages');
        $packagesCheck = $this->checkPackages($io);
        if ($packagesCheck === 'error') {
            $hasErrors = true;
        } elseif ($packagesCheck === 'warning') {
            $hasWarnings = true;
        }

        // Summary
        $io->newLine();
        if (!$hasErrors && !$hasWarnings) {
            $io->success('✅ All checks passed! Your hexagonal architecture is correctly configured.');
        } elseif ($hasErrors) {
            $io->error('❌ Configuration errors found. Please fix them before continuing.');
            $io->writeln('💡 Tip: Run <info>php bin/console make:hexagonal:init</info> to auto-configure basic settings.');
        } else {
            $io->warning('⚠️ Some warnings detected. Your app should work but improvements are recommended.');
        }
    }

    private function checkDoctrine(ConsoleStyle $io): string
    {
        $status = 'success';

        // Check if doctrine.yaml exists
        $doctrinePath = $this->projectDir . '/config/packages/doctrine.yaml';
        if (!file_exists($doctrinePath)) {
            $io->error('❌ config/packages/doctrine.yaml not found');
            return 'error';
        }

        $config = Yaml::parseFile($doctrinePath);

        // Check ORM version
        $composerPath = $this->projectDir . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $ormVersion = $composer['require']['doctrine/orm'] ?? $composer['require-dev']['doctrine/orm'] ?? null;

            if ($ormVersion) {
                if (str_contains($ormVersion, '^3.') || str_contains($ormVersion, '^4.')) {
                    $io->writeln('✅ Doctrine ORM 3.x detected');

                    // Check for XML mappings
                    $mappings = $config['doctrine']['orm']['mappings'] ?? [];
                    $hasYamlMappings = false;
                    foreach ($mappings as $name => $mapping) {
                        if (($mapping['type'] ?? '') === 'yml') {
                            $io->warning("⚠️ YAML mapping detected for '$name' (ORM 3.x should use XML)");
                            $hasYamlMappings = true;
                            $status = 'warning';
                        }
                    }

                    if (!$hasYamlMappings) {
                        $io->writeln('✅ Using XML mappings (recommended for ORM 3.x)');
                    }
                } else {
                    $io->writeln("ℹ️ Doctrine ORM version: $ormVersion");
                }
            }
        }

        // Check for hexagonal mappings
        $mappings = $config['doctrine']['orm']['mappings'] ?? [];
        if (empty($mappings) || count($mappings) === 1) {
            $io->warning('⚠️ No hexagonal module mappings found');
            $io->writeln('   Expected mappings for modules like CadeauAttribution, etc.');
            $status = 'warning';
        } else {
            $io->writeln('✅ ' . count($mappings) . ' mapping(s) configured');
        }

        // Check for custom types
        $types = $config['doctrine']['dbal']['types'] ?? [];
        if (empty($types)) {
            $io->warning('⚠️ No custom Doctrine types found');
            $io->writeln('   Custom types are recommended for ValueObjects');
            $status = 'warning';
        } else {
            $io->writeln('✅ ' . count($types) . ' custom type(s) registered');
        }

        return $status;
    }

    private function checkMessenger(ConsoleStyle $io): string
    {
        $messengerPath = $this->projectDir . '/config/packages/messenger.yaml';

        if (!file_exists($messengerPath)) {
            $io->warning('⚠️ config/packages/messenger.yaml not found');
            return 'warning';
        }

        $config = Yaml::parseFile($messengerPath);
        $buses = $config['framework']['messenger']['buses'] ?? [];

        $hasCommandBus = isset($buses['command.bus']);
        $hasQueryBus = isset($buses['query.bus']);

        if (!$hasCommandBus && !$hasQueryBus) {
            $io->error('❌ No CQRS buses configured (command.bus, query.bus)');
            $io->writeln('   Run: <info>php bin/console make:hexagonal:init</info>');
            return 'error';
        }

        if (!$hasCommandBus) {
            $io->warning('⚠️ command.bus not configured');
            return 'warning';
        }

        if (!$hasQueryBus) {
            $io->warning('⚠️ query.bus not configured');
            return 'warning';
        }

        $io->writeln('✅ CQRS buses configured (command.bus, query.bus)');

        // Check middleware
        $commandMiddleware = $buses['command.bus']['middleware'] ?? [];
        if (!in_array('doctrine_transaction', $commandMiddleware)) {
            $io->warning('⚠️ command.bus missing doctrine_transaction middleware');
            return 'warning';
        }

        $io->writeln('✅ command.bus has doctrine_transaction middleware');

        return 'success';
    }

    private function checkServices(ConsoleStyle $io): string
    {
        $servicesPath = $this->projectDir . '/config/services.yaml';

        if (!file_exists($servicesPath)) {
            $io->error('❌ config/services.yaml not found');
            return 'error';
        }

        $content = file_get_contents($servicesPath);

        // Check for Domain exclusions
        if (!str_contains($content, '/Domain/Model/') && !str_contains($content, '/Domain/ValueObject/')) {
            $io->warning('⚠️ Domain entities/ValueObjects not excluded from autowiring');
            $io->writeln('   Add exclusions in services.yaml:');
            $io->writeln('   <comment>exclude:</comment>');
            $io->writeln('   <comment>  - \'../src/**/Domain/Model/\'</comment>');
            $io->writeln('   <comment>  - \'../src/**/Domain/ValueObject/\'</comment>');
            return 'warning';
        }

        $io->writeln('✅ Domain entities excluded from autowiring');

        // Check for repository bindings
        if (str_contains($content, 'RepositoryInterface:')) {
            $io->writeln('✅ Repository interface bindings found');
        } else {
            $io->warning('⚠️ No repository interface bindings found');
            return 'warning';
        }

        return 'success';
    }

    private function checkRoutes(ConsoleStyle $io): string
    {
        $routesPath = $this->projectDir . '/config/routes.yaml';

        if (!file_exists($routesPath)) {
            $io->warning('⚠️ config/routes.yaml not found');
            return 'warning';
        }

        $content = file_get_contents($routesPath);

        // Check for hexagonal controllers
        if (str_contains($content, '/UI/Http/Web/Controller/') || str_contains($content, 'UI\\Http\\Web\\Controller')) {
            $io->writeln('✅ Hexagonal controllers route configured');
            return 'success';
        }

        $io->warning('⚠️ No hexagonal controller routes found');
        $io->writeln('   Add in routes.yaml:');
        $io->writeln('   <comment>module_controllers:</comment>');
        $io->writeln('   <comment>    resource:</comment>');
        $io->writeln('   <comment>        path: ../src/**/UI/Http/Web/Controller/</comment>');
        $io->writeln('   <comment>        namespace: App</comment>');
        $io->writeln('   <comment>    type: attribute</comment>');

        return 'warning';
    }

    private function checkPackages(ConsoleStyle $io): string
    {
        $composerPath = $this->projectDir . '/composer.json';

        if (!file_exists($composerPath)) {
            $io->error('❌ composer.json not found');
            return 'error';
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $allPackages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        $recommended = [
            'symfony/uid' => 'For generating UUIDs in entities',
            'doctrine/doctrine-fixtures-bundle' => 'For loading test data',
        ];

        $missing = [];
        foreach ($recommended as $package => $reason) {
            if (!isset($allPackages[$package])) {
                $missing[$package] = $reason;
            }
        }

        if (empty($missing)) {
            $io->writeln('✅ All recommended packages installed');
            return 'success';
        }

        $io->warning('⚠️ Some recommended packages are missing:');
        foreach ($missing as $package => $reason) {
            $io->writeln("   • $package - $reason");
            $io->writeln("     Install: <info>composer require $package</info>");
        }

        return 'warning';
    }
}
