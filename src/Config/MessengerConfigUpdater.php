<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Updates config/packages/messenger.yaml automatically
 */
class MessengerConfigUpdater extends ConfigFileUpdater
{
    protected function getConfigFilePath(): string
    {
        return $this->projectDir . '/config/packages/messenger.yaml';
    }

    /**
     * Check if CQRS buses are configured
     */
    public function exists(array $config): bool
    {
        $filePath = $this->getConfigFilePath();

        if (!file_exists($filePath)) {
            return false;
        }

        $data = $this->parseYaml($filePath);
        $buses = $data['framework']['messenger']['buses'] ?? [];

        return isset($buses['command.bus']) && isset($buses['query.bus']);
    }

    /**
     * Add CQRS buses configuration
     *
     * @param array $config (not used, buses are standard)
     */
    public function add(array $config = []): bool
    {
        if ($this->exists($config)) {
            return false; // Already configured
        }

        $filePath = $this->getConfigFilePath();
        $this->backup($filePath);

        try {
            $data = $this->parseYaml($filePath);

            // Ensure structure exists
            if (!isset($data['framework'])) {
                $data['framework'] = [];
            }
            if (!isset($data['framework']['messenger'])) {
                $data['framework']['messenger'] = [];
            }
            if (!isset($data['framework']['messenger']['buses'])) {
                $data['framework']['messenger']['buses'] = [];
            }

            // Set default bus
            $data['framework']['messenger']['default_bus'] = 'command.bus';

            // Add command.bus if missing
            if (!isset($data['framework']['messenger']['buses']['command.bus'])) {
                $data['framework']['messenger']['buses']['command.bus'] = [
                    'middleware' => [
                        'validation',
                        'doctrine_transaction',
                    ],
                ];
            }

            // Add query.bus if missing
            if (!isset($data['framework']['messenger']['buses']['query.bus'])) {
                $data['framework']['messenger']['buses']['query.bus'] = [
                    'middleware' => [
                        'validation',
                    ],
                ];
            }

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }
}
