<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Updates config/routes.yaml automatically
 */
class RoutesConfigUpdater extends ConfigFileUpdater
{
    protected function getConfigFilePath(): string
    {
        return $this->projectDir . '/config/routes.yaml';
    }

    /**
     * Check if routes for a module exist
     */
    public function exists(array $config): bool
    {
        $filePath = $this->getConfigFilePath();

        if (!file_exists($filePath)) {
            return false;
        }

        $moduleKey = $config['route_key'] ?? null;
        if ($moduleKey) {
            $data = $this->parseYaml($filePath);
            return isset($data[$moduleKey]);
        }

        // Fallback: check if path contains the namespace
        $namespace = $config['namespace'] ?? '';
        return $this->fileContains($filePath, $namespace);
    }

    /**
     * Add route configuration for hexagonal controllers
     *
     * @param array $config Expected keys:
     *  - route_key: string (unique key for this route, e.g. "cadeau_attribution_controllers")
     *  - path: string (path to controllers directory)
     *  - namespace: string (namespace prefix)
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

            // Add new route configuration
            $data[$config['route_key']] = [
                'resource' => [
                    'path' => $config['path'],
                    'namespace' => $config['namespace'],
                ],
                'type' => 'attribute',
            ];

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }
}
