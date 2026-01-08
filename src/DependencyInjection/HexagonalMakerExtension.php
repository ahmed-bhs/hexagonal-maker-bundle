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

namespace AhmedBhs\HexagonalMakerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class HexagonalMakerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Use bundle's skeleton dir as default, user can override
        $bundleSkeletonDir = \dirname(__DIR__, 2).'/config/skeleton';
        $skeletonDir = $config['skeleton_dir'];

        // If user skeleton dir doesn't exist, use bundle's default
        if (!is_dir($container->getParameterBag()->resolveValue($skeletonDir))) {
            $skeletonDir = $bundleSkeletonDir;
        }

        $container->setParameter('hexagonal_maker.skeleton_dir', $skeletonDir);
        $container->setParameter('hexagonal_maker.root_dir', $config['root_dir']);
        $container->setParameter('hexagonal_maker.root_namespace', $config['root_namespace']);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');
    }
}
