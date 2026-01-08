<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Base class for updating Symfony configuration files.
 *
 * Provides safe methods to update YAML configuration files
 * while preserving existing content and comments.
 */
abstract class ConfigFileUpdater
{
    protected string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Check if a configuration entry already exists
     */
    abstract public function exists(array $config): bool;

    /**
     * Add a configuration entry if it doesn't exist
     */
    abstract public function add(array $config): bool;

    /**
     * Get the configuration file path
     */
    abstract protected function getConfigFilePath(): string;

    /**
     * Parse YAML file and return array
     */
    protected function parseYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        return Yaml::parse($content) ?? [];
    }

    /**
     * Write array to YAML file
     */
    protected function writeYaml(string $filePath, array $data): void
    {
        $yaml = Yaml::dump($data, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($filePath, $yaml);
    }

    /**
     * Append text to file (for preserving comments)
     */
    protected function appendToFile(string $filePath, string $content): void
    {
        file_put_contents($filePath, $content, FILE_APPEND);
    }

    /**
     * Check if file contains a specific string
     */
    protected function fileContains(string $filePath, string $needle): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        return str_contains($content, $needle);
    }

    /**
     * Backup file before modification
     */
    protected function backup(string $filePath): void
    {
        if (file_exists($filePath)) {
            copy($filePath, $filePath . '.backup');
        }
    }

    /**
     * Restore file from backup
     */
    protected function restore(string $filePath): void
    {
        $backupPath = $filePath . '.backup';
        if (file_exists($backupPath)) {
            copy($backupPath, $filePath);
            unlink($backupPath);
        }
    }
}
