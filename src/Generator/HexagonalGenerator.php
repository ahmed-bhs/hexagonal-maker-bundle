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

namespace AhmedBhs\HexagonalMakerBundle\Generator;

use Symfony\Bundle\MakerBundle\Generator;

class HexagonalGenerator
{
    private Generator $generator;
    private string $skeletonDir;
    private string $rootNamespace;
    private string $rootDir;

    public function __construct(
        Generator $generator,
        string $skeletonDir,
        string $rootNamespace,
        string $rootDir
    ) {
        $this->generator = $generator;
        $this->skeletonDir = $skeletonDir;
        $this->rootNamespace = $rootNamespace;
        $this->rootDir = $rootDir;
    }

    public function generateRepository(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Generate Port (interface in Domain)
        $portNamespace = sprintf(
            '%s\\%s\\Domain\\Port',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $portPath = sprintf(
            '%s/%s/Domain/Port/%sRepositoryInterface.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $entityNamespace = sprintf(
            '%s\\%s\\Domain\\Model',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $portPath,
            $this->skeletonDir.'/src/Module/Domain/Port/RepositoryInterface.tpl.php',
            [
                'namespace' => $portNamespace,
                'class_name' => $name.'RepositoryInterface',
                'entity_name' => $name,
                'entity_namespace' => $entityNamespace,
            ]
        );

        // Generate Adapter (implementation in Infrastructure)
        $adapterNamespace = sprintf(
            '%s\\%s\\Infrastructure\\Persistence\\Doctrine',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $adapterPath = sprintf(
            '%s/%s/Infrastructure/Persistence/Doctrine/Doctrine%sRepository.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $entityNamespace = sprintf(
            '%s\\%s\\Domain\\Model',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $adapterPath,
            $this->skeletonDir.'/src/Module/Infrastructure/Persistence/Doctrine/DoctrineRepository.tpl.php',
            [
                'namespace' => $adapterNamespace,
                'class_name' => 'Doctrine'.$name.'Repository',
                'entity_name' => $name,
                'entity_namespace' => $entityNamespace,
                'port_namespace' => $portNamespace,
                'port_class' => $name.'RepositoryInterface',
            ]
        );
    }

    public function generateEntity(string $path, string $name, array $properties = []): void
    {
        $namespacePath = new NamespacePath($path, '');

        // 1. Generate Domain Entity (PURE - no Doctrine)
        $domainNamespace = sprintf(
            '%s\\%s\\Domain\\Model',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $entityFilePath = sprintf(
            '%s/%s/Domain/Model/%s.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Convert PropertyConfig objects to arrays for template
        $propertyData = array_map(fn($prop) => $prop->toArray(), $properties);

        $this->generator->generateFile(
            $entityFilePath,
            $this->skeletonDir.'/src/Module/Domain/Model/Entity.tpl.php',
            [
                'namespace' => $domainNamespace,
                'class_name' => $name,
                'properties' => $propertyData,
            ]
        );

        // 2. Generate Doctrine ORM Mapping YAML (in Infrastructure)
        $mappingFilePath = sprintf(
            '%s/%s/Infrastructure/Persistence/Doctrine/Orm/Mapping/%s.orm.yml',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $repositoryFullClassName = sprintf(
            '%s\\%s\\Infrastructure\\Persistence\\Doctrine\\Doctrine%sRepository',
            $this->rootNamespace,
            $namespacePath->toNamespace(),
            $name
        );

        $entityFullClassName = sprintf(
            '%s\\%s\\Domain\\Model\\%s',
            $this->rootNamespace,
            $namespacePath->toNamespace(),
            $name
        );

        $this->generator->generateFile(
            $mappingFilePath,
            $this->skeletonDir.'/src/Module/Infrastructure/Persistence/Doctrine/Orm/Mapping/Entity.orm.yml.tpl.php',
            [
                'entity_full_class_name' => $entityFullClassName,
                'repository_full_class_name' => $repositoryFullClassName,
                'entity_name' => $name,
                'properties' => $propertyData,
            ]
        );
    }

    public function generateValueObject(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\Domain\\ValueObject',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Domain/ValueObject/%s.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Domain/ValueObject/ValueObject.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name,
            ]
        );
    }

    public function generateException(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\Domain\\Exception',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Domain/Exception/%s.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Domain/Exception/DomainException.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name,
            ]
        );
    }

    public function generateInput(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\Application\\Input',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Application/Input/%s.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Application/Input/Input.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name,
            ]
        );
    }

    public function generateUseCase(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Extract entity name from use case name (e.g., CreateUser -> User)
        $entityName = preg_replace('/^(Create|Update|Delete|Find|Get|List|Search)/', '', $name);

        $namespace = sprintf(
            '%s\\%s\\Application\\UseCase',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Application/UseCase/%sUseCase.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Command/Response namespaces
        $commandNamespace = sprintf(
            '%s\\%s\\Application\\Command',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $responseNamespace = sprintf(
            '%s\\%s\\Application\\Query',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $repositoryNamespace = sprintf(
            '%s\\%s\\Domain\\Port',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Application/UseCase/UseCase.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'UseCase',
                'command_namespace' => $commandNamespace,
                'command_class' => $name.'Command',
                'response_namespace' => $responseNamespace,
                'response_class' => $name.'Response',
                'repository_namespace' => $repositoryNamespace,
                'repository_class' => $entityName.'RepositoryInterface',
            ]
        );
    }

    public function generateController(string $path, string $name, string $route): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Extract entity name (e.g., CreateUser -> User)
        $entityName = preg_replace('/^(Create|Update|Delete|Show|List|Search)/', '', $name);

        $namespace = sprintf(
            '%s\\%s\\UI\\Http\\Web\\Controller',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/UI/Http/Web/Controller/%sController.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Use case namespace
        $useCaseNamespace = sprintf(
            '%s\\%s\\Application\\UseCase',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Command namespace
        $commandNamespace = sprintf(
            '%s\\%s\\Application\\Command',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Form class
        $formClass = $entityName.'Type';

        // Route name from path and action
        $routeName = sprintf(
            'app.%s.%s',
            strtolower(str_replace('\\', '.', $namespacePath->toNamespace())),
            strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name))
        );

        // Template path
        $templatePath = sprintf(
            '%s/%s.html.twig',
            strtolower(str_replace('\\', '/', $namespacePath->toNamespace())),
            strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name))
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/UI/Http/Web/Controller/Controller.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Controller',
                'use_case_namespace' => $useCaseNamespace,
                'use_case_class' => $name.'UseCase',
                'command_namespace' => $commandNamespace,
                'command_class' => $name.'Command',
                'form_class' => $formClass,
                'route_path' => $route,
                'route_name' => $routeName,
                'template_path' => $templatePath,
            ]
        );
    }

    public function generateForm(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\UI\\Http\\Web\\Form',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/UI/Http/Web/Form/%sType.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Input DTO class (optional)
        $inputClass = $name.'Input';

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/UI/Http/Web/Form/FormType.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Type',
                'input_class' => $inputClass,
            ]
        );
    }

    public function generateCliCommand(string $path, string $name, string $commandName): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\UI\\Cli',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/UI/Cli/%sCommand.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Use case namespace
        $useCaseNamespace = sprintf(
            '%s\\%s\\Application\\UseCase',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Command namespace
        $commandClassNamespace = sprintf(
            '%s\\%s\\Application\\Command',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Command description
        $commandDescription = sprintf(
            'Execute %s operation',
            preg_replace('/([a-z])([A-Z])/', '$1 $2', $name)
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/UI/Cli/CliCommand.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Command',
                'use_case_namespace' => $useCaseNamespace,
                'use_case_class' => $name.'UseCase',
                'command_namespace' => $commandClassNamespace,
                'command_class' => $name.'Command',
                'command_name' => $commandName,
                'command_description' => $commandDescription,
            ]
        );
    }

    public function generateUseCaseTest(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Extract entity name from use case name
        $entityName = preg_replace('/^(Create|Update|Delete|Find|Get|List|Search)/', '', $name);

        $namespace = sprintf(
            '%s\\Tests\\%s\\Application\\%s',
            $this->rootNamespace,
            $namespacePath->toNamespace(),
            $name
        );

        $filePath = sprintf(
            '%s/tests/%s/Application/%s/%sTest.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name,
            $name
        );

        // Use case namespace
        $useCaseNamespace = sprintf(
            '%s\\%s\\Application\\UseCase',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Command namespace
        $commandNamespace = sprintf(
            '%s\\%s\\Application\\Command',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Response namespace
        $responseNamespace = sprintf(
            '%s\\%s\\Application\\Query',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Repository namespace
        $repositoryNamespace = sprintf(
            '%s\\%s\\Domain\\Port',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Exception namespace
        $exceptionNamespace = sprintf(
            '%s\\%s\\Domain\\Exception',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/tests/Application/UseCase/UseCaseTest.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Test',
                'use_case_namespace' => $useCaseNamespace,
                'use_case_class' => $name.'UseCase',
                'command_namespace' => $commandNamespace,
                'command_class' => $name.'Command',
                'response_namespace' => $responseNamespace,
                'response_class' => $name.'Response',
                'repository_namespace' => $repositoryNamespace,
                'repository_class' => $entityName.'RepositoryInterface',
                'exception_namespace' => $exceptionNamespace,
                'exception_class' => 'Invalid'.$entityName.'DataException',
            ]
        );
    }

    public function generateControllerTest(string $path, string $name, string $route): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Extract entity name
        $entityName = preg_replace('/^(Create|Update|Delete|Show|List|Search)/', '', $name);

        $namespace = sprintf(
            '%s\\Tests\\%s\\UI\\Http\\Web\\Controller',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/tests/%s/UI/Http/Web/Controller/%sControllerTest.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Entity namespace
        $entityNamespace = sprintf(
            '%s\\%s\\Domain\\Model',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        // Repository namespace
        $repositoryNamespace = sprintf(
            '%s\\%s\\Domain\\Port',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/tests/UI/Http/Web/Controller/ControllerTest.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'ControllerTest',
                'controller_class' => $name.'Controller',
                'entity_namespace' => $entityNamespace,
                'entity_class' => $entityName,
                'repository_namespace' => $repositoryNamespace,
                'repository_class' => $entityName.'RepositoryInterface',
                'route_path' => $route,
            ]
        );
    }

    public function generateCliCommandTest(string $path, string $name, string $commandName): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Extract entity name
        $entityName = preg_replace('/^(Create|Update|Delete|Find|Get|List|Search)/', '', $name);

        $namespace = sprintf(
            '%s\\Tests\\%s\\UI\\Cli',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/tests/%s/UI/Cli/%sCommandTest.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Repository namespace
        $repositoryNamespace = sprintf(
            '%s\\%s\\Domain\\Port',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/tests/UI/Cli/CliCommandTest.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'CommandTest',
                'command_name' => $commandName,
                'repository_namespace' => $repositoryNamespace,
                'repository_class' => $entityName.'RepositoryInterface',
            ]
        );
    }

    public function generateDomainEvent(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        $namespace = sprintf(
            '%s\\%s\\Domain\\Event',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Domain/Event/%sEvent.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Domain/Event/DomainEvent.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Event',
            ]
        );
    }

    public function generateEventSubscriber(string $path, string $name, string $layer): void
    {
        $namespacePath = new NamespacePath($path, '');

        if ($layer === 'application') {
            $namespace = sprintf(
                '%s\\%s\\Application\\EventSubscriber',
                $this->rootNamespace,
                $namespacePath->toNamespace()
            );

            $filePath = sprintf(
                '%s/%s/Application/EventSubscriber/%sSubscriber.php',
                $this->rootDir,
                $namespacePath->toPath(),
                $name
            );

            // Event namespace
            $eventNamespace = sprintf(
                '%s\\%s\\Domain\\Event',
                $this->rootNamespace,
                $namespacePath->toNamespace()
            );

            $this->generator->generateFile(
                $filePath,
                $this->skeletonDir.'/src/Module/Application/EventSubscriber/EventSubscriber.tpl.php',
                [
                    'namespace' => $namespace,
                    'class_name' => $name.'Subscriber',
                    'event_namespace' => $eventNamespace,
                    'event_class' => $name.'Event',
                ]
            );
        } else {
            // Infrastructure layer
            $namespace = sprintf(
                '%s\\%s\\Infrastructure\\EventSubscriber',
                $this->rootNamespace,
                $namespacePath->toNamespace()
            );

            $filePath = sprintf(
                '%s/%s/Infrastructure/EventSubscriber/%sSubscriber.php',
                $this->rootDir,
                $namespacePath->toPath(),
                $name
            );

            $this->generator->generateFile(
                $filePath,
                $this->skeletonDir.'/src/Module/Infrastructure/EventSubscriber/EventSubscriber.tpl.php',
                [
                    'namespace' => $namespace,
                    'class_name' => $name.'Subscriber',
                ]
            );
        }
    }

    public function generateMessageHandler(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Message Handler in Infrastructure/Messaging/Handler
        $namespace = sprintf(
            '%s\\%s\\Infrastructure\\Messaging\\Handler',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Infrastructure/Messaging/Handler/%sHandler.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        // Message namespace
        $messageNamespace = sprintf(
            '%s\\%s\\Application\\Message',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Infrastructure/Messaging/Handler/MessageHandler.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Handler',
                'message_namespace' => $messageNamespace,
                'message_class' => $name.'Message',
            ]
        );
    }

    public function generateMessage(string $path, string $name): void
    {
        $namespacePath = new NamespacePath($path, '');

        // Message in Application/Message
        $namespace = sprintf(
            '%s\\%s\\Application\\Message',
            $this->rootNamespace,
            $namespacePath->toNamespace()
        );

        $filePath = sprintf(
            '%s/%s/Application/Message/%sMessage.php',
            $this->rootDir,
            $namespacePath->toPath(),
            $name
        );

        $this->generator->generateFile(
            $filePath,
            $this->skeletonDir.'/src/Module/Application/Message/Message.tpl.php',
            [
                'namespace' => $namespace,
                'class_name' => $name.'Message',
            ]
        );
    }
}
