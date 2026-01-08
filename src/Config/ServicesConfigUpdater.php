<?php

declare(strict_types=1);

namespace AhmedBhs\HexagonalMakerBundle\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Updates config/services.yaml automatically
 */
class ServicesConfigUpdater extends ConfigFileUpdater
{
    protected function getConfigFilePath(): string
    {
        return $this->projectDir . '/config/services.yaml';
    }

    /**
     * Check if a service binding exists
     */
    public function exists(array $config): bool
    {
        $filePath = $this->getConfigFilePath();

        if (!file_exists($filePath)) {
            return false;
        }

        $interfaceName = $config['interface'];
        return $this->fileContains($filePath, $interfaceName);
    }

    /**
     * Add repository interface binding
     *
     * @param array $config Expected keys:
     *  - interface: string (FQCN of interface)
     *  - class: string (FQCN of implementation)
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

            // Ensure services section exists
            if (!isset($data['services'])) {
                $data['services'] = [];
            }

            // Add binding
            $data['services'][$config['interface']] = [
                'class' => $config['class'],
            ];

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }

    /**
     * Add Domain exclusions to prevent autowiring entities/VOs
     */
    public function addDomainExclusions(): bool
    {
        $filePath = $this->getConfigFilePath();

        // Check if already excluded
        if ($this->fileContains($filePath, '/Domain/Model/')) {
            return false; // Already excluded
        }

        $this->backup($filePath);

        try {
            $data = $this->parseYaml($filePath);

            // Find App\: section
            if (!isset($data['services']['App\\'])) {
                throw new \RuntimeException('App\\ service configuration not found');
            }

            // Add exclusions
            if (!isset($data['services']['App\\']['exclude'])) {
                $data['services']['App\\']['exclude'] = [];
            }

            $exclusions = [
                '../src/**/Domain/Model/',
                '../src/**/Domain/ValueObject/',
            ];

            foreach ($exclusions as $exclusion) {
                if (!in_array($exclusion, $data['services']['App\\']['exclude'])) {
                    $data['services']['App\\']['exclude'][] = $exclusion;
                }
            }

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }

    /**
     * Add controller argument binding for CQRS buses
     *
     * @param array $config Expected keys:
     *  - controller: string (FQCN of controller)
     *  - bus_type: string ('command.bus' or 'query.bus')
     */
    public function addControllerBinding(array $config): bool
    {
        $controllerFqcn = $config['controller'];

        if ($this->fileContains($this->getConfigFilePath(), $controllerFqcn)) {
            return false; // Already exists
        }

        $filePath = $this->getConfigFilePath();
        $this->backup($filePath);

        try {
            $data = $this->parseYaml($filePath);

            if (!isset($data['services'])) {
                $data['services'] = [];
            }

            // Determine argument name based on bus type
            $argumentName = $config['bus_type'] === 'command.bus' ? '$commandBus' : '$queryBus';

            $data['services'][$controllerFqcn] = [
                'arguments' => [
                    $argumentName => '@' . $config['bus_type'],
                ],
            ];

            $this->writeYaml($filePath, $data);

            return true;
        } catch (\Exception $e) {
            $this->restore($filePath);
            throw $e;
        }
    }
}
