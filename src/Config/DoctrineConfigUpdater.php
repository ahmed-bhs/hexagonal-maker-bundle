<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Updates config/packages/doctrine.yaml automatically
 */
class DoctrineConfigUpdater extends ConfigFileUpdater
{
    protected function getConfigFilePath(): string
    {
        return $this->projectDir . '/config/packages/doctrine.yaml';
    }

    /**
     * Check if mapping exists for a module
     */
    public function exists(array $config): bool
    {
        $mappingName = $config['mapping_name'];
        $filePath = $this->getConfigFilePath();

        if (!file_exists($filePath)) {
            return false;
        }

        $data = $this->parseYaml($filePath);

        return isset($data['doctrine']['orm']['mappings'][$mappingName]);
    }

    /**
     * Add Doctrine mapping for a hexagonal module
     *
     * @param array $config Expected keys:
     *  - mapping_name: string (e.g. "CadeauAttribution")
     *  - type: string (e.g. "xml" or "yml")
     *  - dir: string (path to mapping directory)
     *  - prefix: string (namespace prefix)
     *  - alias: string (optional)
     */
    public function add(array $config): bool
    {
        if ($this->exists($config)) {
            return false; // Already exists
        }

        $filePath = $this->getConfigFilePath();
        $this->backup($filePath);

        try {
            $data = $this->parseYaml($filePath);

            // Ensure structure exists
            if (!isset($data['doctrine'])) {
                $data['doctrine'] = [];
            }
            if (!isset($data['doctrine']['orm'])) {
                $data['doctrine']['orm'] = [];
            }
            if (!isset($data['doctrine']['orm']['mappings'])) {
                $data['doctrine']['orm']['mappings'] = [];
            }

            // Add new mapping
            $mapping = [
                'type' => $config['type'],
                'is_bundle' => false,
                'dir' => $config['dir'],
                'prefix' => $config['prefix'],
            ];

            if (isset($config['alias'])) {
                $mapping['alias'] = $config['alias'];
            }

            $data['doctrine']['orm']['mappings'][$config['mapping_name']] = $mapping;

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }

    /**
     * Add custom Doctrine type
     *
     * @param array $config Expected keys:
     *  - type_name: string (e.g. "habitant_id")
     *  - type_class: string (FQCN of the type class)
     */
    public function addType(array $config): bool
    {
        $typeName = $config['type_name'];
        $filePath = $this->getConfigFilePath();

        // Check if type already exists
        if ($this->fileContains($filePath, $typeName . ':')) {
            return false;
        }

        $this->backup($filePath);

        try {
            $data = $this->parseYaml($filePath);

            // Ensure structure exists
            if (!isset($data['doctrine'])) {
                $data['doctrine'] = [];
            }
            if (!isset($data['doctrine']['dbal'])) {
                $data['doctrine']['dbal'] = [];
            }
            if (!isset($data['doctrine']['dbal']['types'])) {
                $data['doctrine']['dbal']['types'] = [];
            }

            // Add new type
            $data['doctrine']['dbal']['types'][$typeName] = $config['type_class'];

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }
}
