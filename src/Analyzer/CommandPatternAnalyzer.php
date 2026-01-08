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

namespace AhmedBhs\HexagonalMakerBundle\Analyzer;

/**
 * Analyzes command names to detect patterns and infer implementation strategy
 */
final class CommandPatternAnalyzer
{
    /**
     * Detect the pattern from command name
     */
    public function detectPattern(string $commandName): CommandPattern
    {
        // Normalize: remove "Command" suffix if present
        $normalized = str_replace('Command', '', $commandName);

        // Create patterns
        if (preg_match('/^Create/i', $normalized)) {
            return CommandPattern::CREATE;
        }

        // Update patterns
        if (preg_match('/^Update|^Modify|^Change|^Edit/i', $normalized)) {
            return CommandPattern::UPDATE;
        }

        // Delete patterns
        if (preg_match('/^Delete|^Remove/i', $normalized)) {
            return CommandPattern::DELETE;
        }

        // Relation/Association patterns (French & English)
        if (preg_match('/^Attribuer|^Assign|^Associate|^Link|^Attach/i', $normalized)) {
            return CommandPattern::CREATE_RELATION;
        }

        // Activation patterns
        if (preg_match('/^Activate|^Enable|^Activer/i', $normalized)) {
            return CommandPattern::ACTIVATE;
        }

        // Deactivation patterns
        if (preg_match('/^Deactivate|^Disable|^Desactiver/i', $normalized)) {
            return CommandPattern::DEACTIVATE;
        }

        // Status change patterns
        if (preg_match('/^Publish|^Publier|^Archive|^Archiver/i', $normalized)) {
            return CommandPattern::CHANGE_STATUS;
        }

        return CommandPattern::CUSTOM;
    }

    /**
     * Infer entities involved based on command name and pattern
     *
     * @return string[] Array of entity names
     */
    public function inferEntities(string $commandName, CommandPattern $pattern): array
    {
        // Remove pattern prefix
        $entityPart = preg_replace('/^(Create|Update|Delete|Attribuer|Assign|Activate|Deactivate|Publish|Archive|Modify|Change|Edit|Enable|Disable|Activer|Desactiver|Publier|Archiver|Remove|Link|Associate|Attach)/i', '', $commandName);
        $entityPart = str_replace('Command', '', $entityPart);

        // Split camelCase into words
        $words = preg_split('/(?=[A-Z])/', $entityPart, -1, PREG_SPLIT_NO_EMPTY);

        return match ($pattern) {
            CommandPattern::CREATE,
            CommandPattern::UPDATE,
            CommandPattern::DELETE,
            CommandPattern::ACTIVATE,
            CommandPattern::DEACTIVATE,
            CommandPattern::CHANGE_STATUS => [implode('', $words)], // Single entity

            CommandPattern::CREATE_RELATION => $this->inferRelationEntities($words), // Multiple entities

            CommandPattern::CUSTOM => [],
        };
    }

    /**
     * Infer entities for relation pattern
     * Example: "AttribuerCadeaux" -> [Habitant, Cadeau, Attribution]
     */
    private function inferRelationEntities(array $words): array
    {
        // For "Attribuer X", infer: [Subject (context-dependent), X, Association]
        // This is a best-guess - can be overridden with --entities option
        $targetEntity = implode('', $words);

        // Try to create association entity name
        // "Cadeaux" -> "Attribution" (generic name)
        $associationEntity = 'Attribution';

        return [$targetEntity, $associationEntity];
    }

    /**
     * Generate handler implementation code based on pattern
     */
    public function generateHandlerCode(CommandPattern $pattern, array $entities, string $commandVar = '$command'): string
    {
        return match ($pattern) {
            CommandPattern::CREATE => $this->generateCreateCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::UPDATE => $this->generateUpdateCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::DELETE => $this->generateDeleteCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::CREATE_RELATION => $this->generateRelationCode($entities, $commandVar),
            CommandPattern::ACTIVATE => $this->generateActivateCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::DEACTIVATE => $this->generateDeactivateCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::CHANGE_STATUS => $this->generateStatusChangeCode($entities[0] ?? 'Entity', $commandVar),
            CommandPattern::CUSTOM => "        // TODO: Implement your business logic here\n",
        };
    }

    private function generateCreateCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Create new {$entity}
        {$entityVar} = {$entity}::create(
            // TODO: Map command properties to entity constructor
            // Example: {$commandVar}->name, {$commandVar}->email, etc.
        );

        // Persist entity
        {$repoVar}->save({$entityVar});
CODE;
    }

    private function generateUpdateCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Find existing {$entity}
        {$entityVar} = {$repoVar}->findById({$commandVar}->id);
        if (!{$entityVar}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity} with ID "%s" not found', {$commandVar}->id)
            );
        }

        // Update entity
        // TODO: Call domain methods to update entity
        // Example: {$entityVar}->changeName({$commandVar}->name);

        // Persist changes
        {$repoVar}->save({$entityVar});
CODE;
    }

    private function generateDeleteCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Find existing {$entity}
        {$entityVar} = {$repoVar}->findById({$commandVar}->id);
        if (!{$entityVar}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity} with ID "%s" not found', {$commandVar}->id)
            );
        }

        // Delete entity
        {$repoVar}->delete({$entityVar});
CODE;
    }

    private function generateRelationCode(array $entities, string $commandVar): string
    {
        if (count($entities) < 2) {
            return "        // TODO: Implement relation creation logic\n";
        }

        $entity1 = $entities[0];
        $entity2 = $entities[1] ?? 'Association';

        $var1 = '$' . lcfirst($entity1);
        $var2 = '$' . lcfirst($entity2);
        $repo1 = '$' . lcfirst($entity1) . 'Repository';
        $repo2 = '$' . lcfirst($entity2) . 'Repository';

        $prop1 = lcfirst($entity1) . 'Id';
        $prop2 = isset($entities[2]) ? lcfirst($entities[2]) . 'Id' : 'relatedId';

        return <<<CODE
        // Validate first entity exists
        {$var1} = {$repo1}->findById({$commandVar}->{$prop1});
        if (!{$var1}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity1} with ID "%s" not found', {$commandVar}->{$prop1})
            );
        }

        // TODO: Validate second entity if needed

        // Create association
        {$var2} = {$entity2}::create(
            {$commandVar}->{$prop1},
            {$commandVar}->{$prop2}
        );

        // Persist association
        {$repo2}->save({$var2});
CODE;
    }

    private function generateActivateCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Find existing {$entity}
        {$entityVar} = {$repoVar}->findById({$commandVar}->id);
        if (!{$entityVar}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity} with ID "%s" not found', {$commandVar}->id)
            );
        }

        // Activate entity
        {$entityVar}->activate();

        // Persist changes
        {$repoVar}->save({$entityVar});
CODE;
    }

    private function generateDeactivateCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Find existing {$entity}
        {$entityVar} = {$repoVar}->findById({$commandVar}->id);
        if (!{$entityVar}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity} with ID "%s" not found', {$commandVar}->id)
            );
        }

        // Deactivate entity
        {$entityVar}->deactivate();

        // Persist changes
        {$repoVar}->save({$entityVar});
CODE;
    }

    private function generateStatusChangeCode(string $entity, string $commandVar): string
    {
        $entityVar = '$' . lcfirst($entity);
        $repoVar = '$' . lcfirst($entity) . 'Repository';

        return <<<CODE
        // Find existing {$entity}
        {$entityVar} = {$repoVar}->findById({$commandVar}->id);
        if (!{$entityVar}) {
            throw new \InvalidArgumentException(
                sprintf('{$entity} with ID "%s" not found', {$commandVar}->id)
            );
        }

        // Change status
        // TODO: Call appropriate domain method
        // Example: {$entityVar}->publish(); or {$entityVar}->archive();

        // Persist changes
        {$repoVar}->save({$entityVar});
CODE;
    }

    /**
     * Generate repository dependencies for constructor
     */
    public function generateRepositoryDependencies(array $entities): array
    {
        $dependencies = [];

        foreach ($entities as $entity) {
            $repoInterface = $entity . 'RepositoryInterface';
            $varName = lcfirst($entity) . 'Repository';

            $dependencies[] = [
                'interface' => $repoInterface,
                'varName' => $varName,
            ];
        }

        return $dependencies;
    }
}
